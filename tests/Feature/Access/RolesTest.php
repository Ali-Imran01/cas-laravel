<?php

use App\Domain\Identity\Models\User;
use Database\Seeders\AccessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(AccessSeeder::class));

function with_role(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

/** Someone who manages roles but is not a super admin: roles.* and users.view only. */
function role_manager(): User
{
    $role = Role::create(['name' => 'role_manager', 'display_name' => 'Role manager', 'guard_name' => 'web']);
    $role->givePermissionTo(['roles.view', 'roles.create', 'roles.edit', 'roles.delete', 'users.view']);

    return with_role('role_manager');
}

function role_id(string $name): int
{
    return Role::findByName($name)->id;
}

it('is closed to everyone without roles.view', function (string $role) {
    $this->actingAs(with_role($role))->get('/roles')->assertForbidden();
    $this->post('/roles', ['name' => 'x_role', 'display_name' => 'X'])->assertForbidden();
    $this->put('/roles/'.role_id('staff').'/permissions', ['permissions' => []])->assertForbidden();
    $this->delete('/roles/'.role_id('staff'))->assertForbidden();
})->with(['hr_officer', 'dept_head', 'staff']);

it('shows roles with counts, the permission grid and the selected role', function () {
    with_role('hr_officer');

    $this->actingAs(with_role('super_admin'))->get('/roles?role='.role_id('hr_officer'))->assertInertia(fn (Assert $p) => $p
        ->component('Roles/Index')
        ->has('roles', 4)
        ->where('selected.name', 'hr_officer')
        ->where('selected.users_count', 1)
        ->where('selected.is_system', true)
        ->where('selected.locked_reason', null)
        ->where('selected.permissions', ['organization.view', 'users.create', 'users.edit', 'users.view'])
        ->has('grid', 7)
        ->where('grid.4.module', 'approvals')->where('grid.4.actions', ['view', 'create', 'edit', 'delete', 'approve'])
        ->where('can.create', true)->where('can.update', true)->where('can.delete', true));
});

it('marks the super admin role as fixed', function () {
    $this->actingAs(with_role('super_admin'))->get('/roles?role='.role_id('super_admin'))
        ->assertInertia(fn (Assert $p) => $p->where('selected.locked_reason', __('cas.roles.super_admin_fixed')));
});

it('creates a custom role that is not built-in', function () {
    $this->actingAs(with_role('super_admin'));

    $this->post('/roles', ['name' => 'service_desk', 'display_name' => 'Service desk', 'description' => 'Agents', 'is_system' => true])
        ->assertSessionHasNoErrors();

    $role = Role::findByName('service_desk');
    expect($role->getAttribute('is_system'))->toBeFalse()->and($role->getAttribute('display_name'))->toBe('Service desk')->and($role->permissions)->toHaveCount(0);
});

it('validates new roles', function (array $input, string $field) {
    $this->actingAs(with_role('super_admin'))->post('/roles', $input + ['name' => 'ok_role', 'display_name' => 'Ok'])->assertSessionHasErrors($field);
})->with([
    'upper case key' => [['name' => 'Bad'], 'name'],
    'spaces in key' => [['name' => 'bad key'], 'name'],
    'starts with digit' => [['name' => '1role'], 'name'],
    'duplicate key' => [['name' => 'staff'], 'name'],
    'no display name' => [['display_name' => ''], 'display_name'],
]);

it('renames a role but never its key', function () {
    $this->actingAs(with_role('super_admin'));
    $id = role_id('staff');

    $this->put("/roles/$id", ['name' => 'hacked', 'display_name' => 'Team member', 'description' => 'x'])->assertSessionHasNoErrors();

    $role = Role::find($id);
    expect($role->name)->toBe('staff')->and($role->getAttribute('display_name'))->toBe('Team member');
});

it('keeps the super admin role untouchable, even for a super admin', function () {
    $this->actingAs(with_role('super_admin'));
    $id = role_id('super_admin');

    $this->put("/roles/$id", ['display_name' => 'Nope'])->assertSessionHasErrors('role');
    $this->put("/roles/$id/permissions", ['permissions' => ['users.view']])->assertSessionHasErrors('role');
    $this->delete("/roles/$id")->assertSessionHasErrors('role');
    expect(Role::find($id)->permissions)->toHaveCount(29);
});

it('saves the permission grid and applies it to people straight away', function () {
    $head = with_role('dept_head');
    $this->actingAs($head)->get('/users/create')->assertForbidden();

    $this->actingAs(with_role('super_admin'))->put('/roles/'.role_id('dept_head').'/permissions', ['permissions' => ['users.view', 'users.create']])
        ->assertSessionHasNoErrors();

    expect(Role::findByName('dept_head')->permissions->pluck('name')->sort()->values()->all())->toBe(['users.create', 'users.view']);
    $this->actingAs($head->fresh())->get('/users/create')->assertOk();

    $this->actingAs(with_role('super_admin'))->put('/roles/'.role_id('dept_head').'/permissions', ['permissions' => []]);
    $this->actingAs($head->fresh())->get('/users/create')->assertForbidden();
});

it('rejects unknown, repeated or malformed permission lists', function () {
    $this->actingAs(with_role('super_admin'));
    $url = '/roles/'.role_id('staff').'/permissions';

    $this->put($url, ['permissions' => ['users.fly']])->assertSessionHasErrors('permissions.0');
    $this->put($url, ['permissions' => ['users.view', 'users.view']])->assertSessionHasErrors('permissions.0');
    $this->put($url, ['permissions' => 'users.view'])->assertSessionHasErrors('permissions');
    $this->put($url, [])->assertSessionHasErrors('permissions');
    expect(Role::findByName('staff')->permissions)->toHaveCount(0);
});

it('lets someone without super admin grant only permissions they hold', function () {
    $manager = role_manager();
    $custom = Role::create(['name' => 'custom', 'display_name' => 'Custom', 'guard_name' => 'web']);
    $this->actingAs($manager);

    $this->put("/roles/$custom->id/permissions", ['permissions' => ['users.view', 'roles.view']])->assertSessionHasNoErrors();
    $this->put("/roles/$custom->id/permissions", ['permissions' => ['users.view', 'users.create']])->assertSessionHasErrors('permissions'); // not held
    expect($custom->refresh()->permissions->pluck('name')->sort()->values()->all())->toBe(['roles.view', 'users.view']);
});

it('stops someone editing or deleting a role that reaches beyond their own permissions', function () {
    $this->actingAs(role_manager());
    $hr = role_id('hr_officer'); // has users.create/users.edit/organization.view, which the manager lacks

    $this->put("/roles/$hr", ['display_name' => 'Renamed'])->assertSessionHasErrors('role');
    $this->put("/roles/$hr/permissions", ['permissions' => []])->assertSessionHasErrors('role');
    $this->delete("/roles/$hr")->assertSessionHasErrors('role');

    expect(Role::find($hr)->getAttribute('display_name'))->toBe('HR officer')->and(Role::find($hr)->permissions)->toHaveCount(4);
});

it('shows a read-only grid with the reason when the actor may look but not change', function () {
    $this->actingAs(role_manager())->get('/roles?role='.role_id('hr_officer'))
        ->assertInertia(fn (Assert $p) => $p->where('selected.locked_reason', __('cas.roles.above_you'))->where('can.update', true));
});

it('deletes only custom roles nobody holds', function () {
    $this->actingAs(with_role('super_admin'));
    $used = Role::create(['name' => 'used', 'display_name' => 'Used', 'guard_name' => 'web']);
    $free = Role::create(['name' => 'free', 'display_name' => 'Free', 'guard_name' => 'web']);
    with_role('used');

    $this->delete('/roles/'.role_id('staff'))->assertSessionHasErrors('role'); // built-in
    $this->delete("/roles/$used->id")->assertSessionHasErrors('role'); // in use
    $this->delete("/roles/$free->id")->assertRedirect(route('roles.index'));

    expect(Role::find($free->id))->toBeNull()->and(Role::find($used->id))->not->toBeNull();
});

it('does not count deleted users as role holders', function () {
    $this->actingAs(with_role('super_admin'));
    $role = Role::create(['name' => 'gone', 'display_name' => 'Gone', 'guard_name' => 'web']);
    with_role('gone')->delete();

    $this->delete("/roles/$role->id")->assertSessionHasNoErrors();

    expect(Role::find($role->id))->toBeNull();
});

it('tells the browser which permissions to show links for', function (string $role, int $count) {
    $props = $this->actingAs(with_role($role))->get('/')->viewData('page')['props'];

    expect($props['auth']['permissions'])->toHaveCount($count);
})->with(['staff' => ['staff', 0], 'hr_officer' => ['hr_officer', 4], 'super_admin' => ['super_admin', 29]]);
