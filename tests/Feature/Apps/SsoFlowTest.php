<?php

use App\Domain\Apps\Actions\AppAccess;
use App\Domain\Apps\Actions\AppAccessCheck;
use App\Domain\Apps\Actions\RevokeAppTokens;
use App\Domain\Apps\Enums\AppStatus;
use App\Domain\Audit\Models\LoginAttempt;
use App\Domain\Identity\Actions\ChangePassword;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\User;
use Database\Seeders\AccessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(AccessSeeder::class));

it('runs the whole authorization code + PKCE flow, then refreshes with rotation', function () {
    [$app, $secret] = sso_app();
    $user = staff_with_access($app);
    [$verifier, $challenge] = pkce();

    $code = get_code($this, $app, $user, $challenge);
    $tokens = token_request($this, $app, $secret, ['code' => $code, 'code_verifier' => $verifier])->assertOk()
        ->assertJsonStructure(['token_type', 'expires_in', 'access_token', 'refresh_token'])->json();

    expect($tokens['token_type'])->toBe('Bearer')->and($tokens['expires_in'])->toBe(15 * 60);
    $row = DB::table('oauth_access_tokens')->where('user_id', $user->id)->first();
    expect($row->client_id)->toBe($app->oauth_client_id)->and(json_decode($row->scopes, true))->toBe(['openid', 'profile']);

    $attempt = LoginAttempt::latest('id')->first();
    expect($attempt->method->value)->toBe('sso')->and($attempt->result->value)->toBe('success')->and($attempt->application_id)->toBe($app->id);

    $refreshed = token_request($this, $app, $secret, ['grant_type' => 'refresh_token', 'refresh_token' => $tokens['refresh_token']])->assertOk()->json();
    expect($refreshed['refresh_token'])->not->toBe($tokens['refresh_token']);
    token_request($this, $app, $secret, ['grant_type' => 'refresh_token', 'refresh_token' => $tokens['refresh_token']])->assertStatus(400); // old one is spent
});

it('refuses to redeem a code without the right verifier, or twice, or with the wrong secret or redirect', function () {
    [$app, $secret] = sso_app();
    $user = staff_with_access($app);
    [$verifier, $challenge] = pkce();

    $code = get_code($this, $app, $user, $challenge);
    token_request($this, $app, $secret, ['code' => $code])->assertStatus(400); // no verifier
    token_request($this, $app, $secret, ['code' => $code, 'code_verifier' => pkce()[0]])->assertStatus(400)->assertJson(['error' => 'invalid_grant']);

    $code = get_code($this, $app, $user, $challenge);
    token_request($this, $app, 'not-the-secret', ['code' => $code, 'code_verifier' => $verifier])->assertStatus(401);
    token_request($this, $app, $secret, ['code' => $code, 'code_verifier' => $verifier, 'redirect_uri' => 'https://evil.example.com/cb'])->assertStatus(400); // not the URI the code was issued for

    $code = get_code($this, $app, $user, $challenge);
    token_request($this, $app, $secret, ['code' => $code, 'code_verifier' => $verifier])->assertOk();
    token_request($this, $app, $secret, ['code' => $code, 'code_verifier' => $verifier])->assertStatus(400); // codes are single use
});

it('requires PKCE with S256 on every authorization request', function (array $over) {
    [$app] = sso_app();
    $user = staff_with_access($app);
    [, $challenge] = pkce();

    $this->actingAs($user)->get(authorize_url($app, $challenge, $over))
        ->assertStatus(400)->assertInertia(fn (Assert $p) => $p->component('OAuth/Denied')->where('reason', 'pkce_required'));
    expect(DB::table('oauth_auth_codes')->count())->toBe(0);
})->with([
    'no challenge' => [['code_challenge' => '']],
    'plain method' => [['code_challenge_method' => 'plain']],
    'no method' => [['code_challenge_method' => '']],
    'too short' => [['code_challenge' => 'abc']],
    'bad characters' => [['code_challenge' => str_repeat('!', 43)]],
]);

it('sends signed-out visitors to sign in and remembers where they were going', function () {
    [$app] = sso_app();
    [, $challenge] = pkce();
    $url = authorize_url($app, $challenge);

    $this->get($url)->assertRedirect(route('login'));

    $intended = (string) session('url.intended');
    parse_str((string) parse_url($intended, PHP_URL_QUERY), $got);
    parse_str((string) parse_url($url, PHP_URL_QUERY), $want);
    expect($intended)->toStartWith('http://localhost/oauth/authorize?')->and($got)->toEqual($want);
});

it('shows a no-access page, and logs it against the app, when the person has no way in', function () {
    [$app] = sso_app();
    $user = User::factory()->create(); // no role mapped, no grant
    [, $challenge] = pkce();

    $this->actingAs($user)->get(authorize_url($app, $challenge))
        ->assertForbidden()->assertInertia(fn (Assert $p) => $p->component('OAuth/Denied')->where('reason', 'no_access')->where('app', 'Asset Inspection'));

    $attempt = LoginAttempt::latest('id')->first();
    expect($attempt->result->value)->toBe('blocked')->and($attempt->failure_reason)->toBe('no_access')->and($attempt->application_id)->toBe($app->id)
        ->and(DB::table('oauth_auth_codes')->count())->toBe(0);
});

it('refuses a disabled app, an inactive person, unknown apps and scopes the app may not ask for', function () {
    [$app] = sso_app();
    $user = staff_with_access($app);
    [, $challenge] = pkce();
    $this->actingAs($user);

    $this->get(authorize_url($app, $challenge, ['scope' => 'openid users.read']))->assertStatus(400)
        ->assertInertia(fn (Assert $p) => $p->where('reason', 'invalid_scope'));
    $this->get(authorize_url($app, $challenge, ['client_id' => '00000000-0000-4000-8000-000000000000']))->assertStatus(400)
        ->assertInertia(fn (Assert $p) => $p->where('reason', 'unknown_app')->where('app', null));

    $user->forceFill(['status' => UserStatus::Inactive])->save();
    $this->get(authorize_url($app, $challenge))->assertForbidden()->assertInertia(fn (Assert $p) => $p->where('reason', 'user_inactive'));
    $user->forceFill(['status' => UserStatus::Active])->save();

    $app->forceFill(['status' => AppStatus::Disabled])->save();
    $this->get(authorize_url($app, $challenge))->assertForbidden()->assertInertia(fn (Assert $p) => $p->where('reason', 'app_disabled'));
    expect(DB::table('oauth_auth_codes')->count())->toBe(0);
});

it('grants access by role, by individual grant, and lets a grant expire', function () {
    [$app] = sso_app();
    $check = app(AppAccessCheck::class);
    $viaRole = staff_with_access($app, 'Viewer');
    $granted = User::factory()->create();
    $expiring = User::factory()->create();
    $admin = User::factory()->create();

    app(AppAccess::class)->grantUser($admin, $app, $granted, 'Agent', null);
    app(AppAccess::class)->grantUser($admin, $app, $expiring, 'Agent', now()->addDay());

    expect($check->denial($viaRole, $app))->toBeNull()->and($check->appRole($viaRole, $app))->toBe('Viewer')
        ->and($check->denial($granted, $app))->toBeNull()->and($check->appRole($granted, $app))->toBe('Agent')
        ->and($check->denial($expiring, $app))->toBeNull();

    $this->travel(2)->days();

    expect($check->denial($expiring->fresh(), $app))->toBe('no_access');
});

it('prefers an individual grant over a role mapping, and picks the first role alphabetically otherwise', function () {
    [$app] = sso_app();
    $user = staff_with_access($app, 'Viewer'); // staff -> Viewer
    $user->assignRole('hr_officer');
    app(AppAccess::class)->mapRole($app, Role::findByName('hr_officer'), 'Editor');
    $check = app(AppAccessCheck::class);

    expect($check->appRole($user, $app))->toBe('Editor'); // hr_officer sorts before staff

    app(AppAccess::class)->grantUser(User::factory()->create(), $app, $user, 'Owner', null);
    expect($check->appRole($user->fresh(), $app))->toBe('Owner');
});

it('accepts only the grants the flow needs', function () {
    [$app, $secret] = sso_app();

    foreach (['client_credentials', 'password'] as $grant) {
        $response = token_request($this, $app, $secret, ['grant_type' => $grant, 'username' => 'x', 'password' => 'y']);
        expect($response->getStatusCode())->toBeIn([400, 401])->and($response->json('access_token'))->toBeNull();
    }
});

it('does not expose the rest of Passport\'s routes', function () {
    $this->actingAs(User::factory()->create());

    foreach (['/oauth/device', '/oauth/device/authorize', '/oauth/clients', '/oauth/tokens', '/oauth/scopes', '/oauth/personal-access-tokens'] as $path) {
        $this->get($path)->assertNotFound();
    }
    $this->post('/oauth/token/refresh')->assertNotFound();
    $this->post('/oauth/clients', ['name' => 'x', 'redirect' => 'https://x.example.com'])->assertNotFound();
    $this->post('/oauth/device/code')->assertNotFound();
});

it('ends app sessions when the person is locked, deactivated, deleted or changes their password', function (string $how) {
    [$app, $secret] = sso_app();
    $user = staff_with_access($app);
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');
    signed_in_via_app($app, $user, $secret);
    expect(tokens_revoked($user))->toBeFalse();

    $this->actingAs($admin);
    match ($how) {
        'lock' => $this->post("/users/$user->id/lock"),
        'deactivate' => $this->post("/users/$user->id/deactivate"),
        'delete' => $this->delete("/users/$user->id"),
        'password' => app(ChangePassword::class)($user, 'Brand-New-Passw0rd!x1'),
    };

    expect(tokens_revoked($user))->toBeTrue();
})->with(['lock', 'deactivate', 'delete', 'password']);

it('ends app sessions when a role change or removed access takes the person out of an app, but not otherwise', function () {
    [$app, $secret] = sso_app();
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');
    $this->actingAs($admin);

    // Role change: staff -> hr_officer, and only staff is mapped.
    $user = staff_with_access($app);
    signed_in_via_app($app, $user, $secret);
    $this->actingAs($admin)->put("/users/$user->id", ['staff_id' => $user->staff_id, 'name' => 'x', 'email' => $user->email, 'role' => 'hr_officer']);
    expect(tokens_revoked($user))->toBeTrue();

    // Unmapping the role revokes its holders...
    $second = staff_with_access($app);
    signed_in_via_app($app, $second, $secret);
    $this->actingAs($admin)->delete("/apps/$app->id/roles/".Role::findByName('staff')->id);
    expect(tokens_revoked($second))->toBeTrue();

    // ...but not people who still have another way in.
    app(AppAccess::class)->mapRole($app, Role::findByName('staff'), 'Viewer');
    $third = staff_with_access($app);
    app(AppAccess::class)->grantUser($admin, $app, $third, 'Agent', null);
    signed_in_via_app($app, $third, $secret);
    $this->actingAs($admin)->delete("/apps/$app->id/roles/".Role::findByName('staff')->id);
    expect(tokens_revoked($third))->toBeFalse();

    // Removing the last way in revokes.
    $this->actingAs($admin)->delete("/apps/$app->id/users/$third->id");
    expect(tokens_revoked($third))->toBeTrue();
});

it('revokes tokens of people whose time-limited access ran out, via the scheduled command', function () {
    [$app, $secret] = sso_app();
    $user = User::factory()->create();
    $admin = User::factory()->create();
    app(AppAccess::class)->grantUser($admin, $app, $user, 'Agent', now()->addDay());
    signed_in_via_app($app, $user, $secret);

    $this->artisan('apps:revoke-expired')->assertSuccessful();
    expect(tokens_revoked($user))->toBeFalse(); // still valid

    $this->travel(2)->days();
    $this->artisan('apps:revoke-expired')->assertSuccessful();

    expect(tokens_revoked($user))->toBeTrue();
});

it('does not touch other apps or other people when revoking', function () {
    [$one, $secretOne] = sso_app(['code' => 'one']);
    [$two, $secretTwo] = sso_app(['code' => 'two']);
    $user = staff_with_access($one);
    app(AppAccess::class)->mapRole($two, Role::findByName('staff'), 'Viewer');
    $bystander = staff_with_access($one);
    signed_in_via_app($one, $user, $secretOne);
    signed_in_via_app($two, $user, $secretTwo);
    signed_in_via_app($one, $bystander, $secretOne);

    app(RevokeAppTokens::class)->forUser($user, $one);

    expect(DB::table('oauth_access_tokens')->where('user_id', $user->id)->where('client_id', $one->oauth_client_id)->where('revoked', false)->exists())->toBeFalse()
        ->and(DB::table('oauth_access_tokens')->where('user_id', $user->id)->where('client_id', $two->oauth_client_id)->where('revoked', false)->exists())->toBeTrue()
        ->and(tokens_revoked($bystander))->toBeFalse();
});
