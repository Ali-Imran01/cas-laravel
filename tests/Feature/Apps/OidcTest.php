<?php

use App\Domain\Apps\Actions\AppAccess;
use App\Domain\Apps\Models\Application;
use App\Domain\Audit\Models\AuditLog;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\User;
use App\Domain\Organization\Models\OrgUnit;
use App\Domain\Organization\Models\Position;
use App\Domain\Sso\IdTokens;
use App\Domain\Sso\Issuer;
use App\Domain\Sso\OidcKeys;
use Database\Seeders\AccessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Lcobucci\JWT\Token\Plain;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(AccessSeeder::class));

/** Runs the whole flow for a person and returns the token response (with id_token when openid was asked for). */
function oidc_login(Application $app, string $secret, User $user, array $authorize = [], array $tokenExtra = []): array
{
    [$verifier, $challenge] = pkce();
    $code = get_code(test(), $app, $user, $challenge, $authorize);

    return token_request(test(), $app, $secret, ['code' => $code, 'code_verifier' => $verifier] + $tokenExtra)->assertOk()->json();
}

/** A request with a bearer token; the guard cache is reset so earlier requests in the same test cannot leak in. */
function bearer(?string $token)
{
    app('auth')->forgetGuards();

    return $token ? test()->withToken($token) : test()->withoutToken();
}

function parse_jwt(string $jwt): Plain
{
    return Configuration::forUnsecuredSigner()->parser()->parse($jwt);
}

it('publishes discovery metadata that matches the real endpoints', function () {
    $response = $this->getJson('/.well-known/openid-configuration')->assertOk();

    $response->assertJson([
        'issuer' => Issuer::url(),
        'authorization_endpoint' => Issuer::url().'/oauth/authorize',
        'token_endpoint' => Issuer::url().'/oauth/token',
        'userinfo_endpoint' => Issuer::url().'/oauth/userinfo',
        'jwks_uri' => Issuer::url().'/oauth/jwks',
        'end_session_endpoint' => Issuer::url().'/oauth/logout',
        'response_types_supported' => ['code'],
        'grant_types_supported' => ['authorization_code', 'refresh_token'],
        'id_token_signing_alg_values_supported' => ['RS256'],
        'code_challenge_methods_supported' => ['S256'],
    ]);
    expect($response->json('scopes_supported'))->toBe(array_keys(config('cas.sso.scopes')))
        ->and($response->headers->get('Access-Control-Allow-Origin'))->toBe('*');

    foreach (['authorization_endpoint', 'token_endpoint', 'userinfo_endpoint', 'jwks_uri', 'end_session_endpoint'] as $key) {
        expect(parse_url($response->json($key), PHP_URL_PATH))->toBeIn(['/oauth/authorize', '/oauth/token', '/oauth/userinfo', '/oauth/jwks', '/oauth/logout']);
    }
});

it('publishes the signing key as a JWK that matches the key on disk', function () {
    $jwks = $this->getJson('/oauth/jwks')->assertOk()->json();
    $key = $jwks['keys'][0];

    expect($jwks['keys'])->toHaveCount(1)
        ->and($key)->toMatchArray(['kty' => 'RSA', 'use' => 'sig', 'alg' => 'RS256'])
        ->and($key['kid'])->toBe(app(OidcKeys::class)->kid());

    $rsa = openssl_pkey_get_details(openssl_pkey_get_public(app(OidcKeys::class)->publicKey()))['rsa'];
    $decode = fn (string $v) => base64_decode(strtr($v, '-_', '+/'));
    expect($decode($key['n']))->toBe($rsa['n'])->and($decode($key['e']))->toBe($rsa['e']);
    expect(json_encode($jwks))->not->toContain('PRIVATE')->not->toContain('"d"');
});

it('issues a signed ID token with the right claims for the granted scopes', function () {
    [$app, $secret] = sso_app();
    $unit = OrgUnit::factory()->create(['code' => 'ICT-APD', 'name' => 'App Dev']);
    $position = Position::factory()->create(['org_unit_id' => $unit->id, 'title' => 'Analyst']);
    $user = staff_with_access($app, 'Viewer');
    $user->forceFill(['org_unit_id' => $unit->id, 'position_id' => $position->id, 'last_login_at' => now()->subMinute()])->save();

    $tokens = oidc_login($app, $secret, $user, ['scope' => 'openid profile email', 'nonce' => 'n-0S6_WzA2Mj']);

    $verified = app(IdTokens::class)->parseOwn($tokens['id_token']);
    expect($verified)->not->toBeNull();
    $claims = $verified->claims()->all();
    expect($verified->headers()->get('alg'))->toBe('RS256')->and($verified->headers()->get('kid'))->toBe(app(OidcKeys::class)->kid())
        ->and($claims['iss'])->toBe(Issuer::url())->and($claims['aud'])->toBe([$app->oauth_client_id])->and($claims['azp'])->toBe($app->oauth_client_id)
        ->and($claims['sub'])->toBe((string) $user->id)->and($claims['nonce'])->toBe('n-0S6_WzA2Mj')
        ->and($claims['name'])->toBe($user->name)->and($claims['staff_id'])->toBe($user->staff_id)->and($claims['preferred_username'])->toBe($user->staff_id)
        ->and($claims['org_unit'])->toBe('App Dev')->and($claims['org_unit_code'])->toBe('ICT-APD')->and($claims['position'])->toBe('Analyst')
        ->and($claims['app_role'])->toBe('Viewer')->and($claims['email'])->toBe($user->email)->and($claims['email_verified'])->toBeTrue()
        ->and($claims['auth_time'])->toBe($user->last_login_at->timestamp);
    expect($claims['exp']->getTimestamp() - $claims['iat']->getTimestamp())->toBe(10 * 60);
});

it('only puts claims in the ID token that the granted scopes allow', function () {
    [$app, $secret] = sso_app();
    $user = staff_with_access($app);

    $claims = app(IdTokens::class)->parseOwn(oidc_login($app, $secret, $user, ['scope' => 'openid'])['id_token'])->claims()->all();

    expect($claims)->toHaveKeys(['iss', 'sub', 'aud', 'exp', 'iat'])->not->toHaveKeys(['name', 'staff_id', 'app_role', 'email', 'nonce']);
});

it('issues no ID token without the openid scope', function () {
    [$app, $secret] = sso_app();

    $tokens = oidc_login($app, $secret, staff_with_access($app), ['scope' => 'profile email']);

    expect($tokens)->not->toHaveKey('id_token')->and($tokens)->toHaveKey('access_token');
});

it('binds the nonce to its own code, and leaves it out of the ID token issued on refresh', function () {
    [$app, $secret] = sso_app();
    $user = staff_with_access($app);
    [$v1, $c1] = pkce();
    [$v2, $c2] = pkce();
    $code1 = get_code($this, $app, $user, $c1, ['nonce' => 'first-nonce']);
    $code2 = get_code($this, $app, $user, $c2, ['nonce' => 'second-nonce']);

    $second = token_request($this, $app, $secret, ['code' => $code2, 'code_verifier' => $v2])->assertOk()->json();
    $first = token_request($this, $app, $secret, ['code' => $code1, 'code_verifier' => $v1])->assertOk()->json();

    $nonce = fn (array $t) => app(IdTokens::class)->parseOwn($t['id_token'])->claims()->get('nonce');
    expect($nonce($first))->toBe('first-nonce')->and($nonce($second))->toBe('second-nonce');

    $refreshed = token_request($this, $app, $secret, ['grant_type' => 'refresh_token', 'refresh_token' => $first['refresh_token']])->assertOk()->json();
    expect(app(IdTokens::class)->parseOwn($refreshed['id_token'])->claims()->has('nonce'))->toBeFalse();
});

it('rejects ID tokens signed by anyone else', function () {
    [$app, $secret] = sso_app();
    $real = oidc_login($app, $secret, staff_with_access($app), ['scope' => 'openid'])['id_token'];

    $forgedKey = openssl_pkey_new(['private_key_bits' => 2048]);
    openssl_pkey_export($forgedKey, $pem);
    $config = Configuration::forAsymmetricSigner(new Sha256, InMemory::plainText($pem), InMemory::plainText(openssl_pkey_get_details($forgedKey)['key']));
    $forged = $config->builder()->issuedBy(Issuer::url())->relatedTo('1')->getToken($config->signer(), $config->signingKey())->toString();
    $wrongIssuer = Configuration::forAsymmetricSigner(new Sha256, InMemory::plainText(app(OidcKeys::class)->privateKey()), InMemory::plainText(app(OidcKeys::class)->publicKey()));
    $imposter = $wrongIssuer->builder()->issuedBy('https://evil.example.com')->relatedTo('1')->getToken($wrongIssuer->signer(), $wrongIssuer->signingKey())->toString();

    $ids = app(IdTokens::class);
    expect($ids->parseOwn($real))->not->toBeNull()->and($ids->parseOwn($forged))->toBeNull()->and($ids->parseOwn($imposter))->toBeNull()
        ->and($ids->parseOwn('not.a.jwt'))->toBeNull()->and($ids->parseOwn(''))->toBeNull();
});

it('re-checks access when a code is redeemed and when a token is refreshed', function () {
    [$app, $secret] = sso_app();
    $user = staff_with_access($app);
    [$verifier, $challenge] = pkce();

    // Access disappears between authorize and token (no revocation hook ran, e.g. a direct database change).
    $code = get_code($this, $app, $user, $challenge);
    DB::table('application_role')->delete();
    token_request($this, $app, $secret, ['code' => $code, 'code_verifier' => $verifier])->assertStatus(400)->assertJson(['error' => 'invalid_grant']);
    expect(tokens_revoked($user))->toBeTrue();

    // ...or between two refreshes.
    app(AppAccess::class)->mapRole($app, Role::findByName('staff'), 'Viewer');
    $tokens = oidc_login($app, $secret, $user);
    $user->forceFill(['status' => UserStatus::Inactive])->save();
    token_request($this, $app, $secret, ['grant_type' => 'refresh_token', 'refresh_token' => $tokens['refresh_token']])->assertStatus(400)->assertJson(['error' => 'invalid_grant']);
    expect(tokens_revoked($user))->toBeTrue();
});

it('serves userinfo for the token scopes and nothing more', function () {
    [$app, $secret] = sso_app();
    $user = staff_with_access($app, 'Agent');
    $full = oidc_login($app, $secret, $user, ['scope' => 'openid profile email']);
    $minimal = oidc_login($app, $secret, $user, ['scope' => 'openid']);

    $body = bearer($full['access_token'])->getJson('/oauth/userinfo')->assertOk()->json();
    expect($body)->toMatchArray(['sub' => (string) $user->id, 'name' => $user->name, 'staff_id' => $user->staff_id, 'app_role' => 'Agent', 'email' => $user->email, 'email_verified' => true]);

    expect(bearer($minimal['access_token'])->getJson('/oauth/userinfo')->assertOk()->json())->toBe(['sub' => (string) $user->id]);
    bearer($full['access_token'])->postJson('/oauth/userinfo')->assertOk()->assertJsonPath('sub', (string) $user->id);
    expect(bearer($full['access_token'])->getJson('/oauth/userinfo')->headers->get('Cache-Control'))->toContain('no-store');
});

it('answers userinfo with 401 JSON, never a redirect, without a valid token', function () {
    [$app, $secret] = sso_app();
    $tokens = oidc_login($app, $secret, staff_with_access($app), ['scope' => 'openid']);

    bearer(null)->get('/oauth/userinfo')->assertStatus(401)->assertJsonMissingPath('sub');
    bearer('garbage')->getJson('/oauth/userinfo')->assertStatus(401);
    bearer(null)->postJson('/oauth/userinfo')->assertStatus(401);

    DB::table('oauth_access_tokens')->update(['revoked' => true]);
    bearer($tokens['access_token'])->getJson('/oauth/userinfo')->assertStatus(401);
});

it('refuses userinfo without the openid scope, and once the person lost access', function () {
    [$app, $secret] = sso_app();
    $user = staff_with_access($app);
    $noOpenid = oidc_login($app, $secret, $user, ['scope' => 'profile']);
    $ok = oidc_login($app, $secret, $user, ['scope' => 'openid profile']);

    bearer($noOpenid['access_token'])->getJson('/oauth/userinfo')->assertStatus(403)->assertJson(['error' => 'insufficient_scope']);

    DB::table('application_role')->delete(); // access gone, tokens not yet revoked
    bearer($ok['access_token'])->getJson('/oauth/userinfo')->assertStatus(401)->assertJson(['error' => 'invalid_token']);
});

it('signs a person out everywhere from an app\'s server', function () {
    [$one, $secretOne] = sso_app(['code' => 'one']);
    [$two, $secretTwo] = sso_app(['code' => 'two']);
    $user = staff_with_access($one);
    app(AppAccess::class)->mapRole($two, Role::findByName('staff'), 'Viewer');
    $tokenOne = oidc_login($one, $secretOne, $user);
    oidc_login($two, $secretTwo, $user);
    DB::table('sessions')->insert(['id' => 'browser-1', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);

    bearer($tokenOne['access_token'])->postJson('/oauth/logout')->assertNoContent();

    expect(tokens_revoked($user))->toBeTrue()->and(DB::table('sessions')->where('user_id', $user->id)->exists())->toBeFalse();
    bearer($tokenOne['access_token'])->getJson('/oauth/userinfo')->assertStatus(401);
    $log = AuditLog::where('action', 'LOGOUT')->latest('id')->firstOrFail();
    expect($log->actor_id)->toBe($user->id)->and($log->new_values)->toBe(['via' => 'api']);
    bearer(null)->postJson('/oauth/logout')->assertStatus(401);
});

it('ends the browser session on an RP-initiated logout and returns only to a registered origin', function () {
    [$app, $secret] = sso_app();
    $user = staff_with_access($app);
    $tokens = oidc_login($app, $secret, $user, ['scope' => 'openid']);
    DB::table('sessions')->insert(['id' => 'browser-1', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);
    $hint = $tokens['id_token'];

    $this->actingAs($user)->get('/oauth/logout?'.http_build_query(['id_token_hint' => $hint, 'post_logout_redirect_uri' => 'https://ams.example.com/signed-out', 'state' => 'xyz']))
        ->assertRedirect('https://ams.example.com/signed-out?state=xyz');

    $this->assertGuest();
    expect(tokens_revoked($user))->toBeTrue()->and(DB::table('sessions')->where('user_id', $user->id)->exists())->toBeFalse();
    expect(AuditLog::where('action', 'LOGOUT')->latest('id')->first()->new_values)->toBe(['via' => 'oidc']);
});

it('will not redirect a logout to an unregistered address', function (string $target) {
    [$app, $secret] = sso_app();
    $user = staff_with_access($app);
    $hint = oidc_login($app, $secret, $user, ['scope' => 'openid'])['id_token'];

    $this->actingAs($user)->get('/oauth/logout?'.http_build_query(['id_token_hint' => $hint, 'post_logout_redirect_uri' => $target]))
        ->assertRedirect(route('login'));
})->with([
    'other host' => ['https://evil.example.com/out'],
    'look-alike host' => ['https://ams.example.com.evil.com/out'],
    'other scheme' => ['http://ams.example.com/out'],
    'other port' => ['https://ams.example.com:8443/out'],
    'not a URL' => ['javascript:alert(1)'],
    'relative' => ['/out'],
]);

it('refuses a browser logout without a valid ID token hint and leaves the session alone', function (string $hint) {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/oauth/logout?'.http_build_query(['id_token_hint' => $hint]))
        ->assertStatus(400)->assertInertia(fn ($p) => $p->component('OAuth/Denied')->where('reason', 'invalid_logout'));

    $this->assertAuthenticatedAs($user);
})->with(['none' => [''], 'garbage' => ['abc.def.ghi'], 'not a jwt' => ['hello']]);

it('accepts an expired ID token as a logout hint', function () {
    [$app, $secret] = sso_app();
    $user = staff_with_access($app);
    $hint = oidc_login($app, $secret, $user, ['scope' => 'openid'])['id_token'];

    $this->travel(2)->hours();

    $this->actingAs($user)->get('/oauth/logout?'.http_build_query(['id_token_hint' => $hint]))->assertRedirect(route('login'));
    $this->assertGuest();
});

it('exposes claims about the person from the app they signed in to, not from others', function () {
    [$one, $secretOne] = sso_app(['code' => 'one']);
    [$two, $secretTwo] = sso_app(['code' => 'two']);
    $user = staff_with_access($one, 'Viewer');
    app(AppAccess::class)->mapRole($two, Role::findByName('staff'), 'Admin');

    $forOne = bearer(oidc_login($one, $secretOne, $user, ['scope' => 'openid profile'])['access_token'])->getJson('/oauth/userinfo')->json();
    $forTwo = bearer(oidc_login($two, $secretTwo, $user, ['scope' => 'openid profile'])['access_token'])->getJson('/oauth/userinfo')->json();

    expect($forOne['app_role'])->toBe('Viewer')->and($forTwo['app_role'])->toBe('Admin');
});
