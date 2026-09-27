<?php

// Shared by the SSO feature tests (authorization-code flow, OpenID Connect, API).

use App\Domain\Apps\Actions\AppAccess;
use App\Domain\Apps\Models\Application;
use App\Domain\Identity\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\ClientRepository;
use Spatie\Permission\Models\Role;

const REDIRECT = 'https://ams.example.com/callback';

/** @return array{0: Application, 1: string} the app and its client secret */
function sso_app(array $attrs = []): array
{
    $client = app(ClientRepository::class)->createAuthorizationCodeGrantClient('Asset Inspection', [REDIRECT], confidential: true);
    $app = Application::create($attrs + [
        'oauth_client_id' => $client->getKey(), 'code' => 'ams', 'name' => 'Asset Inspection',
        'environment' => 'production', 'status' => 'active', 'allowed_scopes' => ['openid', 'profile', 'email'],
    ]);

    return [$app, (string) $client->plainSecret];
}

/** @return array{0: string, 1: string} PKCE verifier and its S256 challenge */
function pkce(): array
{
    $verifier = rtrim(strtr(base64_encode(random_bytes(48)), '+/', '-_'), '=');

    return [$verifier, rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=')];
}

function authorize_url(Application $app, string $challenge, array $over = []): string
{
    return '/oauth/authorize?'.http_build_query($over + [
        'client_id' => $app->oauth_client_id, 'redirect_uri' => REDIRECT, 'response_type' => 'code',
        'scope' => 'openid profile', 'state' => 'abc12345', 'code_challenge' => $challenge, 'code_challenge_method' => 'S256',
    ]);
}

function staff_with_access(Application $app, string $appRole = 'Viewer'): User
{
    $user = User::factory()->create();
    $user->assignRole('staff');
    app(AppAccess::class)->mapRole($app, Role::findByName('staff', 'web'), $appRole);

    return $user;
}

/** Runs the browser half of the flow and returns the authorization code. */
function get_code($test, Application $app, User $user, string $challenge, array $over = []): string
{
    $response = $test->actingAs($user)->get(authorize_url($app, $challenge, $over));
    $response->assertRedirectContains(REDIRECT);
    parse_str((string) parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);
    expect($query['state'] ?? null)->toBe($over['state'] ?? 'abc12345');

    return $query['code'];
}

function token_request($test, Application $app, string $secret, array $over = [])
{
    return $test->postJson('/oauth/token', $over + [
        'grant_type' => 'authorization_code', 'client_id' => $app->oauth_client_id, 'client_secret' => $secret,
        'redirect_uri' => REDIRECT,
    ]);
}

/** A user with a live token (and refresh token) for the app. */
function signed_in_via_app(Application $app, User $user, string $secret): array
{
    [$verifier, $challenge] = pkce();
    $code = get_code(test(), $app, $user, $challenge);

    return token_request(test(), $app, $secret, ['code' => $code, 'code_verifier' => $verifier])->assertOk()->json();
}

function tokens_revoked(User $user): bool
{
    return DB::table('oauth_access_tokens')->where('user_id', $user->id)->where('revoked', false)->doesntExist()
        && DB::table('oauth_refresh_tokens')->whereIn('access_token_id', DB::table('oauth_access_tokens')->where('user_id', $user->id)->select('id'))->where('revoked', false)->doesntExist();
}

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
