<?php

use App\Domain\Apps\Enums\AppStatus;
use App\Domain\Apps\Models\Application;
use App\Domain\Audit\Models\AuditLog;
use App\Domain\Identity\Models\User;
use Database\Seeders\AccessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(AccessSeeder::class));

function apps_admin(): User
{
    $user = User::factory()->create();
    $user->assignRole('super_admin');

    return $user;
}

function app_payload(array $over = []): array
{
    return $over + [
        'code' => 'asset-inspection', 'name' => 'Asset Inspection', 'environment' => 'production',
        'homepage_url' => 'https://ams.example.com', 'color' => '#2563eb',
        'allowed_scopes' => ['openid', 'profile', 'email'],
        'redirect_uris' => "https://ams.example.com/callback\nhttp://localhost:3000/callback",
    ];
}

/** Registers through the real endpoint as a super admin and returns the app plus the one-time secret. */
function registered_app(array $over = []): array
{
    test()->actingAs(apps_admin())->post('/apps', app_payload($over))->assertSessionHasNoErrors();

    return [Application::where('code', $over['code'] ?? 'asset-inspection')->firstOrFail(), session('appSecret')];
}

it('is closed to everyone without apps permissions', function (string $role) {
    $user = User::factory()->create();
    $user->assignRole($role);
    $this->actingAs($user);
    [$app] = registered_app(); // registers as a super admin, then we return to the low-privilege user
    $this->actingAs($user);

    $this->get('/apps')->assertForbidden();
    $this->post('/apps', app_payload(['code' => 'other']))->assertForbidden();
    $this->put("/apps/$app->id", app_payload())->assertForbidden();
    $this->post("/apps/$app->id/secret")->assertForbidden();
    $this->post("/apps/$app->id/disable")->assertForbidden();
    $this->delete("/apps/$app->id")->assertForbidden();
    $this->post("/apps/$app->id/roles", ['role_id' => 1, 'app_role' => 'x'])->assertForbidden();
    $this->post("/apps/$app->id/users", ['staff_id' => 'x', 'app_role' => 'x'])->assertForbidden();
})->with(['hr_officer', 'dept_head', 'staff']);

it('registers an app with a confidential Passport client and shows the secret exactly once', function () {
    [$app, $secret] = registered_app();

    $client = $app->client;
    expect($secret)->toBeString()->toHaveLength(40)
        ->and($client->name)->toBe('Asset Inspection')
        ->and($client->confidential())->toBeTrue()
        ->and($client->redirect_uris)->toBe(['https://ams.example.com/callback', 'http://localhost:3000/callback'])
        ->and($client->grant_types)->toBe(['authorization_code', 'refresh_token'])
        ->and($client->revoked)->toBeFalse()
        ->and($client->skipsAuthorization(User::factory()->make(), []))->toBeTrue();
    // Only a hash is stored.
    expect(DB::table('oauth_clients')->where('id', $client->id)->value('secret'))->not->toBe($secret)
        ->and(Hash::check($secret, DB::table('oauth_clients')->where('id', $client->id)->value('secret')))->toBeTrue();

    expect($app->status)->toBe(AppStatus::Active)->and($app->allowed_scopes)->toBe(['openid', 'profile', 'email'])->and($app->color)->toBe('#2563eb');

    // The follow-up page load carries the secret in flash; the next one does not.
    $this->get("/apps?app=$app->id")->assertInertia(fn (Assert $p) => $p->where('flash.appSecret', $secret));
    $this->get("/apps?app=$app->id")->assertInertia(fn (Assert $p) => $p->where('flash.appSecret', null));
});

it('keeps the secret out of props, the audit log and the database in the clear', function () {
    [$app, $secret] = registered_app();

    $json = json_encode($this->get("/apps?app=$app->id")->viewData('page')['props']);
    $this->get('/apps'); // flash consumed

    $props = json_encode($this->get("/apps?app=$app->id")->viewData('page')['props']);
    expect($props)->not->toContain($secret)->not->toContain('"secret"');
    expect(json_encode(AuditLog::all()->map->getAttributes()))->not->toContain($secret);
    expect($json)->toBeString();
});

it('registers a sandbox app as sandbox and audits the registration', function () {
    [$app] = registered_app(['code' => 'qr-sandbox', 'environment' => 'sandbox']);

    $log = AuditLog::where('action', 'CREATE')->where('auditable_type', Application::class)->firstOrFail();
    expect($app->status)->toBe(AppStatus::Sandbox)
        ->and($log->new_values['code'])->toBe('qr-sandbox')->and($log->new_values['client_id'])->toBe($app->oauth_client_id);
});

it('validates codes, names, scopes and colours', function (array $over, string $field) {
    $this->actingAs(apps_admin())->post('/apps', app_payload($over))->assertSessionHasErrors($field);
    expect(Application::count())->toBe(0);
})->with([
    'code with spaces' => [['code' => 'bad code'], 'code'],
    'code too short' => [['code' => 'a'], 'code'],
    'no name' => [['name' => ''], 'name'],
    'bad environment' => [['environment' => 'live'], 'environment'],
    'unknown scope' => [['allowed_scopes' => ['openid', 'root']], 'allowed_scopes.1'],
    'no scopes' => [['allowed_scopes' => []], 'allowed_scopes'],
    'bad colour' => [['color' => 'blue'], 'color'],
    'bad homepage' => [['homepage_url' => 'javascript:alert(1)'], 'homepage_url'],
]);

it('rejects duplicate codes', function () {
    registered_app();

    $this->post('/apps', app_payload())->assertSessionHasErrors('code');
    expect(Application::count())->toBe(1);
});

it('only accepts redirect URIs that cannot leak a code', function (string $uri, bool $ok) {
    $this->actingAs(apps_admin())->post('/apps', app_payload(['redirect_uris' => $uri]));

    expect(Application::count())->toBe($ok ? 1 : 0);
})->with([
    'https' => ['https://app.example.com/cb', true],
    'https with port and query' => ['https://app.example.com:8443/cb?x=1', true],
    'http localhost' => ['http://localhost:3000/cb', true],
    'http loopback ip' => ['http://127.0.0.1:8000/cb', true],
    'plain http on a real host' => ['http://app.example.com/cb', false],
    'javascript scheme' => ['javascript:alert(1)', false],
    'custom scheme' => ['myapp://callback', false],
    'wildcard' => ['https://*.example.com/cb', false],
    'fragment' => ['https://app.example.com/cb#x', false],
    'credentials in the address' => ['https://user:pass@app.example.com/cb', false],
    'relative path' => ['/callback', false],
    'no host' => ['https:///cb', false],
    'look-alike localhost' => ['http://localhost.evil.com/cb', false],
]);

it('limits and de-duplicates redirect URIs', function () {
    $this->actingAs(apps_admin());

    $this->post('/apps', app_payload(['redirect_uris' => "https://a.example.com/cb\nhttps://a.example.com/cb"]))->assertSessionHasErrors('redirect_uris.1');
    $this->post('/apps', app_payload(['redirect_uris' => implode("\n", array_map(fn ($i) => "https://a$i.example.com/cb", range(1, 11)))]))->assertSessionHasErrors('redirect_uris');
    $this->post('/apps', app_payload(['redirect_uris' => '']))->assertSessionHasErrors('redirect_uris');
    expect(Application::count())->toBe(0);
});

it('updates settings and keeps the Passport client in step, but never the code', function () {
    [$app] = registered_app();

    $this->put("/apps/$app->id", app_payload([
        'code' => 'hijacked', 'name' => 'Renamed', 'environment' => 'staging', 'allowed_scopes' => ['openid'],
        'redirect_uris' => 'https://new.example.com/cb',
    ]))->assertSessionHasNoErrors();

    $app->refresh();
    expect($app->code)->toBe('asset-inspection')->and($app->name)->toBe('Renamed')->and($app->environment->value)->toBe('staging')
        ->and($app->allowed_scopes)->toBe(['openid'])
        ->and($app->client->name)->toBe('Renamed')->and($app->client->redirect_uris)->toBe(['https://new.example.com/cb']);

    $log = AuditLog::where('action', 'UPDATE')->latest('id')->firstOrFail();
    expect($log->old_values['redirect_uris'])->toBe(['https://ams.example.com/callback', 'http://localhost:3000/callback'])
        ->and($log->new_values['redirect_uris'])->toBe(['https://new.example.com/cb']);
});

it('rotates the secret so the old one stops working, without storing the new one in the clear', function () {
    [$app, $old] = registered_app();

    $this->post("/apps/$app->id/secret")->assertSessionHasNoErrors();
    $new = session('appSecret');

    $hash = DB::table('oauth_clients')->where('id', $app->oauth_client_id)->value('secret');
    expect($new)->not->toBe($old)->and(Hash::check($new, $hash))->toBeTrue()->and(Hash::check($old, $hash))->toBeFalse()
        ->and($app->refresh()->secret_rotated_at)->not->toBeNull();
    $log = AuditLog::where('action', 'ROTATE_SECRET')->firstOrFail();
    expect(json_encode($log->getAttributes()))->not->toContain($new);
});

it('disabling revokes the client and every token, and enabling restores it', function () {
    [$app] = registered_app();
    $user = User::factory()->create();
    DB::table('oauth_access_tokens')->insert(['id' => 'tok1', 'user_id' => $user->id, 'client_id' => $app->oauth_client_id, 'scopes' => '["openid"]', 'revoked' => false, 'created_at' => now(), 'updated_at' => now(), 'expires_at' => now()->addHour()]);
    DB::table('oauth_refresh_tokens')->insert(['id' => 'ref1', 'access_token_id' => 'tok1', 'revoked' => false, 'expires_at' => now()->addDay()]);

    $this->post("/apps/$app->id/disable")->assertSessionHasNoErrors();

    expect($app->refresh()->status)->toBe(AppStatus::Disabled)->and($app->disabled_at)->not->toBeNull()
        ->and($app->client->revoked)->toBeTrue()
        ->and((bool) DB::table('oauth_access_tokens')->where('id', 'tok1')->value('revoked'))->toBeTrue()
        ->and((bool) DB::table('oauth_refresh_tokens')->where('id', 'ref1')->value('revoked'))->toBeTrue();
    $this->post("/apps/$app->id/disable")->assertSessionHasErrors('app');

    $this->post("/apps/$app->id/enable")->assertSessionHasNoErrors();
    expect($app->refresh()->status)->toBe(AppStatus::Active)->and($app->disabled_at)->toBeNull()->and($app->client->revoked)->toBeFalse();
    $this->post("/apps/$app->id/enable")->assertSessionHasErrors('app');
    expect(AuditLog::whereIn('action', ['DISABLE', 'ENABLE'])->orderBy('id')->pluck('action')->all())->toBe(['DISABLE', 'ENABLE']);
});

it('deletes only a disabled app, taking its client, tokens and access rules along', function () {
    [$app] = registered_app();
    $role = Role::findByName('staff');
    $user = User::factory()->create();
    $this->post("/apps/$app->id/roles", ['role_id' => $role->id, 'app_role' => 'Viewer']);
    $this->post("/apps/$app->id/users", ['staff_id' => $user->staff_id, 'app_role' => 'Agent']);
    DB::table('oauth_access_tokens')->insert(['id' => 'tok1', 'user_id' => $user->id, 'client_id' => $app->oauth_client_id, 'scopes' => '[]', 'revoked' => true, 'created_at' => now(), 'updated_at' => now(), 'expires_at' => now()->addHour()]);
    DB::table('oauth_refresh_tokens')->insert(['id' => 'ref1', 'access_token_id' => 'tok1', 'revoked' => true, 'expires_at' => now()->addDay()]);

    $this->delete("/apps/$app->id")->assertSessionHasErrors('app');
    expect(Application::count())->toBe(1);

    $this->post("/apps/$app->id/disable");
    $this->delete("/apps/$app->id")->assertRedirect(route('apps.index'));

    expect(Application::count())->toBe(0)->and(DB::table('oauth_clients')->count())->toBe(0)
        ->and(DB::table('oauth_access_tokens')->count())->toBe(0)->and(DB::table('oauth_refresh_tokens')->count())->toBe(0)
        ->and(DB::table('application_role')->count())->toBe(0)->and(DB::table('application_user')->count())->toBe(0);
    expect(AuditLog::where('action', 'DELETE')->latest('id')->first()->old_values['code'])->toBe('asset-inspection');
});

it('maps CAS roles to app roles and lets a mapping be changed or removed', function () {
    [$app] = registered_app();
    $role = Role::findByName('staff');

    $this->post("/apps/$app->id/roles", ['role_id' => $role->id, 'app_role' => 'Viewer'])->assertSessionHasNoErrors();
    $this->post("/apps/$app->id/roles", ['role_id' => $role->id, 'app_role' => 'Agent'])->assertSessionHasNoErrors();

    expect($app->roles()->count())->toBe(1)->and($app->roles->first()->pivot->app_role)->toBe('Agent');
    expect(AuditLog::where('action', 'ACCESS_GRANT')->latest('id')->first()->old_values['app_role'])->toBe('Viewer');

    $this->post("/apps/$app->id/roles", ['role_id' => 999999, 'app_role' => 'X'])->assertSessionHasErrors('role_id');
    $this->post("/apps/$app->id/roles", ['role_id' => $role->id, 'app_role' => '<script>'])->assertSessionHasErrors('app_role');

    $this->delete("/apps/$app->id/roles/$role->id");
    expect($app->roles()->count())->toBe(0)->and(AuditLog::where('action', 'ACCESS_REVOKE')->count())->toBe(1);
});

it('grants individual people access by staff ID, optionally until a date', function () {
    [$app] = registered_app();
    $user = User::factory()->create(['staff_id' => 'STF-777']);
    $expires = now()->addDays(10)->toDateString();

    $this->post("/apps/$app->id/users", ['staff_id' => ' stf-777 ', 'app_role' => 'Agent', 'expires_at' => $expires])->assertSessionHasNoErrors();

    $grant = $app->users()->first();
    expect($grant->id)->toBe($user->id)->and($grant->pivot->app_role)->toBe('Agent')->and(substr((string) $grant->pivot->expires_at, 0, 10))->toBe($expires)
        ->and($grant->pivot->granted_by)->not->toBeNull();

    $this->post("/apps/$app->id/users", ['staff_id' => 'NOBODY', 'app_role' => 'Agent'])->assertSessionHasErrors('staff_id');
    $this->post("/apps/$app->id/users", ['staff_id' => 'STF-777', 'app_role' => 'Agent', 'expires_at' => now()->subDay()->toDateString()])->assertSessionHasErrors('expires_at');

    $this->delete("/apps/$app->id/users/$user->id");
    expect($app->users()->count())->toBe(0);
});

it('shows the selected app with its credentials, rules and endpoints', function () {
    [$app] = registered_app();
    $role = Role::findByName('staff');
    $this->post("/apps/$app->id/roles", ['role_id' => $role->id, 'app_role' => 'Viewer']);
    $this->get('/apps'); // consume the flash

    $this->get("/apps?app=$app->id")->assertInertia(fn (Assert $p) => $p
        ->component('Apps/Index')->has('apps', 1)
        ->where('selected.code', 'asset-inspection')->where('selected.client_id', $app->oauth_client_id)
        ->where('selected.redirect_uris', ['https://ams.example.com/callback', 'http://localhost:3000/callback'])
        ->where('selected.roles.0.app_role', 'Viewer')
        ->where('endpoints.authorize', route('passport.authorizations.authorize'))->where('endpoints.token', route('passport.token'))
        ->has('scopes', 6)->where('defaultScopes', ['openid', 'profile', 'email'])->where('can.delete', true));
});
