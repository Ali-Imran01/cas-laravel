<?php

use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\User;
use App\Domain\Organization\Models\OrgUnit;
use Database\Seeders\AccessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(AccessSeeder::class));

function signedInAs(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

it('turns away guests and users without users.view', function () {
    $this->get('/users')->assertRedirect(route('login'));
    $this->actingAs(signedInAs('staff'))->get('/users')->assertForbidden();
});

it('lists users for anyone holding users.view', function (string $role) {
    User::factory()->count(3)->create();

    $this->actingAs(signedInAs($role))->get('/users')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Users/Index')->has('users.data', 4)->has('orgUnits')->has('roles')->where('can.create', $role === 'hr_officer'));
})->with(['hr_officer', 'dept_head']);

it('searches name, staff id and email case-insensitively and treats % and _ literally', function () {
    User::factory()->create(['name' => 'Aisyah Rahman', 'staff_id' => 'STF-1', 'email' => 'aisyah@x.test']);
    User::factory()->create(['name' => '50% Off_Man', 'staff_id' => 'STF-2', 'email' => 'off@x.test']);
    User::factory()->create(['name' => 'Someone Else', 'staff_id' => 'STF-3', 'email' => 'else@x.test']);
    $this->actingAs(signedInAs('hr_officer'));

    foreach (['AISYAH', 'stf-1', 'AISYAH@X.TEST'] as $term) {
        $this->get('/users?search='.urlencode($term))->assertInertia(fn (Assert $p) => $p->has('users.data', 1)->where('users.data.0.staff_id', 'STF-1'));
    }
    $this->get('/users?search='.urlencode('50%'))->assertInertia(fn (Assert $p) => $p->has('users.data', 1)->where('users.data.0.staff_id', 'STF-2'));
    $this->get('/users?search='.urlencode('%'))->assertInertia(fn (Assert $p) => $p->has('users.data', 1)); // not a wildcard
});

it('filters by status, role and org unit including everything below it', function () {
    $hq = OrgUnit::factory()->create(['code' => 'HQ']);
    $div = OrgUnit::factory()->create(['code' => 'DIV', 'parent_id' => $hq->id]);
    $other = OrgUnit::factory()->create(['code' => 'OTH']);
    $inDiv = User::factory()->create(['org_unit_id' => $div->id]);
    User::factory()->create(['org_unit_id' => $other->id, 'status' => UserStatus::Locked]);
    $inDiv->assignRole('dept_head');
    $this->actingAs(signedInAs('hr_officer'));

    $this->get('/users?org_unit='.$hq->id)->assertInertia(fn (Assert $p) => $p->has('users.data', 1)->where('users.data.0.id', $inDiv->id));
    $this->get('/users?status=locked')->assertInertia(fn (Assert $p) => $p->has('users.data', 1));
    $this->get('/users?role=dept_head')->assertInertia(fn (Assert $p) => $p->has('users.data', 1)->where('users.data.0.id', $inDiv->id));
});

it('rejects unknown filter values and sort columns', function () {
    $this->actingAs(signedInAs('hr_officer'));

    $this->get('/users?status=banana')->assertSessionHasErrors('status');
    $this->get('/users?sort=password')->assertSessionHasErrors('sort');
});

it('sorts and paginates 15 per page keeping the query string', function () {
    User::factory()->count(20)->sequence(fn ($s) => ['name' => sprintf('User %02d', $s->index)])->create();
    $this->actingAs(signedInAs('hr_officer'));

    $this->get('/users?sort=name&dir=desc')->assertInertia(fn (Assert $p) => $p
        ->has('users.data', 15)->where('users.data.0.name', 'User 19')->where('users.last_page', 2)
        ->where('users.next_page_url', fn ($url) => str_contains($url, 'sort=name') && str_contains($url, 'dir=desc')));
});

it('never sends secrets to the browser', function () {
    $user = User::factory()->create();
    $user->forceFill(['mfa_secret' => 'JBSWY3DPEHPK3PXP', 'mfa_recovery_codes' => ['aaaaa-11111']])->save();

    $json = json_encode($this->actingAs(signedInAs('hr_officer'))->get('/users')->viewData('page')['props']);

    expect($json)->not->toContain('JBSWY3DPEHPK3PXP')->not->toContain('aaaaa-11111')->not->toContain('"password"')->not->toContain('remember_token');
});
