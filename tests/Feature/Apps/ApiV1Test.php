<?php

use App\Domain\Apps\Actions\AppAccess;
use App\Domain\Apps\Enums\AppStatus;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\User;
use App\Domain\Organization\Enums\OrgUnitType;
use App\Domain\Organization\Models\OrgUnit;
use App\Domain\Organization\Models\Position;
use Database\Seeders\AccessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(AccessSeeder::class));

/** An app allowed to read the directory, a person with access, and a bearer token for them with the given scopes. */
function api_token(string $scope = 'openid users.read org.read', array $appAttrs = []): array
{
    [$app, $secret] = sso_app($appAttrs + ['allowed_scopes' => ['openid', 'profile', 'email', 'users.read', 'org.read']]);
    $user = staff_with_access($app);
    $tokens = oidc_login($app, $secret, $user, ['scope' => $scope]);

    return [$tokens['access_token'], $app, $user, $secret];
}

const USER_KEYS = ['id', 'staff_id', 'name', 'email', 'active', 'org_unit', 'position', 'updated_at'];

it('answers 401 JSON to callers without a working token', function () {
    [$token] = api_token();

    bearer(null)->get('/api/v1/users')->assertStatus(401)->assertJson(['message' => 'Unauthenticated.']);
    bearer('garbage')->getJson('/api/v1/users')->assertStatus(401);
    bearer(null)->get('/api/v1/org-units')->assertStatus(401);

    DB::table('oauth_access_tokens')->update(['revoked' => true]);
    bearer($token)->getJson('/api/v1/users')->assertStatus(401);
});

it('needs the scope for each part of the API', function () {
    [$usersOnly] = api_token('openid users.read');
    [$orgOnly] = api_token('openid org.read', ['code' => 'other']);
    [$neither] = api_token('openid', ['code' => 'third']);

    bearer($usersOnly)->getJson('/api/v1/users')->assertOk();
    bearer($usersOnly)->getJson('/api/v1/org-units')->assertStatus(403);
    bearer($orgOnly)->getJson('/api/v1/org-units')->assertOk();
    bearer($orgOnly)->getJson('/api/v1/users')->assertStatus(403);
    bearer($neither)->getJson('/api/v1/users')->assertStatus(403);
    bearer($neither)->getJson('/api/v1/org-units/1')->assertStatus(403);
});

it('stops working when the app is disabled or the person loses access, even before the token is revoked', function () {
    [$token, $app] = api_token();
    bearer($token)->getJson('/api/v1/users')->assertOk();

    DB::table('application_role')->delete();
    bearer($token)->getJson('/api/v1/users')->assertStatus(401)->assertJson(['error' => 'invalid_token']);

    app(AppAccess::class)->mapRole($app, Role::findByName('staff', 'web'), 'Viewer');
    bearer($token)->getJson('/api/v1/users')->assertOk();

    $app->forceFill(['status' => AppStatus::Disabled])->save();
    bearer($token)->getJson('/api/v1/users')->assertStatus(401);
});

it('lists active people with directory fields only', function () {
    [$token, , $me] = api_token();
    $unit = OrgUnit::factory()->create(['code' => 'ICT', 'name' => 'ICT']);
    $position = Position::factory()->create(['org_unit_id' => $unit->id, 'title' => 'Analyst', 'grade' => 'F41']);
    $ada = User::factory()->create(['name' => 'Ada Lovelace', 'org_unit_id' => $unit->id, 'position_id' => $position->id]);
    $ada->forceFill(['mfa_secret' => 'JBSWY3DPEHPK3PXP', 'failed_login_count' => 3, 'last_login_ip' => '10.0.0.9'])->save();
    User::factory()->create(['status' => UserStatus::Inactive]);
    User::factory()->create(['status' => UserStatus::Locked]);
    User::factory()->pending()->create();
    User::factory()->create()->delete();

    $response = bearer($token)->getJson('/api/v1/users')->assertOk();
    $rows = collect($response->json('data'));

    expect($rows->pluck('id')->all())->toBe([(string) $me->id, (string) $ada->id]);
    $row = $rows->firstWhere('id', (string) $ada->id);
    expect(array_keys($row))->toBe(USER_KEYS)
        ->and($row['org_unit'])->toBe(['id' => $unit->id, 'code' => 'ICT', 'name' => 'ICT'])
        ->and($row['position'])->toBe(['id' => $position->id, 'title' => 'Analyst', 'grade' => 'F41'])->and($row['active'])->toBeTrue();
    expect(json_encode($response->json()))->not->toContain('JBSWY3DPEHPK3PXP')->not->toContain('10.0.0.9')->not->toContain('password')->not->toContain('mfa');
    expect($response->headers->get('Cache-Control'))->toContain('no-store');
});

it('filters, searches and pages the directory', function () {
    [$token] = api_token();
    $hq = OrgUnit::factory()->create(['code' => 'HQ']);
    $div = OrgUnit::factory()->create(['code' => 'DIV', 'parent_id' => $hq->id]);
    User::factory()->create(['name' => 'Zed Zeta', 'org_unit_id' => $div->id]);
    User::factory()->create(['name' => 'Yan Yotta', 'org_unit_id' => $hq->id]);
    User::factory()->create(['name' => 'Gone Gary', 'status' => UserStatus::Inactive]);
    foreach (range(1, 30) as $i) {
        User::factory()->create(['name' => "Filler $i"]);
    }
    $get = fn (string $qs) => bearer($token)->getJson("/api/v1/users?$qs");

    expect(collect($get('search=zeta')->json('data'))->pluck('name')->all())->toBe(['Zed Zeta'])
        ->and(collect($get("org_unit=$hq->id")->json('data'))->pluck('name')->sort()->values()->all())->toBe(['Yan Yotta', 'Zed Zeta'])
        ->and(collect($get('status=inactive')->json('data'))->pluck('name')->all())->toBe(['Gone Gary'])
        ->and($get('search=%25')->json('meta.total'))->toBe(0); // a literal percent sign, not a wildcard

    $page = $get('per_page=10')->json();
    expect($page['data'])->toHaveCount(10)->and($page['meta'])->toMatchArray(['current_page' => 1, 'per_page' => 10, 'total' => 33, 'last_page' => 4])
        ->and($page['links']['next'])->toContain('page=2')->and($page['links']['next'])->toContain('per_page=10')->and($page['links']['prev'])->toBeNull();
    expect($get('per_page=10&page=4')->json('data'))->toHaveCount(3);
    expect(count($get('')->json('data')))->toBe(25); // default page size
});

it('filters by last change so apps can sync', function () {
    [$token] = api_token();
    $old = User::factory()->create(['name' => 'Old']);
    DB::table('users')->where('id', $old->id)->update(['updated_at' => now()->subDays(10)]);
    User::factory()->create(['name' => 'Fresh']);

    $names = collect(bearer($token)->getJson('/api/v1/users?updated_since='.urlencode(now()->subDay()->toIso8601String()))->json('data'))->pluck('name');

    expect($names)->toContain('Fresh')->not->toContain('Old');
});

it('rejects bad parameters with 422 JSON', function (string $qs, string $field) {
    [$token] = api_token();

    bearer($token)->getJson("/api/v1/users?$qs")->assertStatus(422)->assertJsonValidationErrors($field);
})->with([
    'page size too large' => ['per_page=101', 'per_page'],
    'page size zero' => ['per_page=0', 'per_page'],
    'unknown status' => ['status=locked', 'status'],
    'bad date' => ['updated_since=whenever', 'updated_since'],
    'bad unit' => ['org_unit=abc', 'org_unit'],
]);

it('shows one person, including someone who left, but never deleted or non-active-state accounts', function () {
    [$token] = api_token();
    $active = User::factory()->create();
    $left = User::factory()->create(['status' => UserStatus::Inactive]);
    $locked = User::factory()->create(['status' => UserStatus::Locked]);
    $deleted = User::factory()->create();
    $deleted->delete();

    bearer($token)->getJson("/api/v1/users/$active->id")->assertOk()->assertJsonPath('data.id', (string) $active->id);
    bearer($token)->getJson("/api/v1/users/$left->id")->assertOk()->assertJsonPath('data.active', false);
    bearer($token)->getJson("/api/v1/users/$locked->id")->assertStatus(404)->assertJson(['error' => 'not_found']);
    bearer($token)->getJson("/api/v1/users/$deleted->id")->assertStatus(404);
    bearer($token)->getJson('/api/v1/users/999999')->assertStatus(404);
    bearer($token)->getJson('/api/v1/users/abc')->assertStatus(404);
});

it('serves the organization tree in order with parents, depth and heads', function () {
    [$token] = api_token();
    $hq = OrgUnit::factory()->create(['code' => 'HQ', 'name' => 'HQ', 'type' => OrgUnitType::Headquarters]);
    $div = OrgUnit::factory()->create(['code' => 'DIV', 'name' => 'Div', 'type' => OrgUnitType::Division, 'parent_id' => $hq->id]);
    $unit = OrgUnit::factory()->create(['code' => 'UNIT', 'name' => 'Unit', 'type' => OrgUnitType::Unit, 'parent_id' => $div->id, 'is_active' => false]);
    $other = OrgUnit::factory()->create(['code' => 'OTHER', 'name' => 'Other', 'type' => OrgUnitType::Division, 'parent_id' => $hq->id]);
    $head = User::factory()->create(['org_unit_id' => $div->id]);
    $div->update(['head_user_id' => $head->id]);
    $gone = OrgUnit::factory()->create(['code' => 'GONE', 'parent_id' => $hq->id]);
    $gone->delete();

    $rows = collect(bearer($token)->getJson('/api/v1/org-units')->assertOk()->json('data'));

    expect($rows->pluck('code')->all())->toBe(['HQ', 'DIV', 'UNIT', 'OTHER'])
        ->and($rows->pluck('depth')->all())->toBe([0, 1, 2, 1])
        ->and($rows->pluck('parent_id')->all())->toBe([null, $hq->id, $div->id, $hq->id])
        ->and($rows->firstWhere('code', 'UNIT')['is_active'])->toBeFalse()
        ->and($rows->firstWhere('code', 'DIV')['head'])->toBe(['id' => (string) $head->id, 'staff_id' => $head->staff_id, 'name' => $head->name])
        ->and($rows->firstWhere('code', 'HQ')['head'])->toBeNull()
        ->and($rows->first()['type'])->toBe('headquarters');
    expect(array_keys($rows->first()))->toBe(['id', 'code', 'name', 'type', 'parent_id', 'depth', 'is_active', 'head']);

    $one = bearer($token)->getJson("/api/v1/org-units/$hq->id")->assertOk()->json('data');
    expect($one['children'])->toBe([$div->id, $other->id])->and($one['depth'])->toBe(0);
    bearer($token)->getJson("/api/v1/org-units/$gone->id")->assertStatus(404);
    bearer($token)->getJson('/api/v1/org-units/999999')->assertStatus(404);
});

it('is read-only', function () {
    [$token] = api_token();

    foreach (['post', 'put', 'patch', 'delete'] as $method) {
        bearer($token)->{$method.'Json'}('/api/v1/users', ['name' => 'x'])->assertStatus(405);
    }
    bearer($token)->deleteJson('/api/v1/users/1')->assertStatus(405);
    bearer($token)->postJson('/api/v1/org-units', [])->assertStatus(405);
});

it('rate limits per app and person, not per address', function () {
    config(['cas.api.rate_limit' => 3]);
    [$tokenA] = api_token();
    [$tokenB] = api_token('openid users.read org.read', ['code' => 'other']);

    foreach (range(1, 3) as $_) {
        bearer($tokenA)->getJson('/api/v1/users')->assertOk();
    }
    bearer($tokenA)->getJson('/api/v1/users')->assertStatus(429);
    bearer($tokenB)->getJson('/api/v1/users')->assertOk(); // same address, different app and person
});

it('does not accept a web session as credentials', function () {
    $this->actingAs(User::factory()->create());

    app('auth')->forgetGuards();
    $this->withoutToken()->getJson('/api/v1/users')->assertStatus(401);
});
