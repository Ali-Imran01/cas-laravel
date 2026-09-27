<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/** The permission grid (module x action) and the four built-in roles. */
class AccessSeeder extends Seeder
{
    public const MODULES = ['users', 'roles', 'organization', 'apps', 'approvals', 'audit', 'settings'];

    public const ACTIONS = ['view', 'create', 'edit', 'delete'];

    /** @var array<string, array{display: string, description: string, permissions: list<string>}> */
    public const ROLES = [
        'super_admin' => ['display' => 'Super admin', 'description' => 'Everything, including other admins.', 'permissions' => ['*']],
        'hr_officer' => ['display' => 'HR officer', 'description' => 'Manages staff accounts.', 'permissions' => ['users.view', 'users.create', 'users.edit', 'organization.view']],
        'dept_head' => ['display' => 'Department head', 'description' => 'Reviews and approves requests for their unit.', 'permissions' => ['users.view', 'approvals.view', 'approvals.approve']],
        'staff' => ['display' => 'Staff', 'description' => 'Signs in to connected apps.', 'permissions' => []],
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $names = [];
        foreach (self::MODULES as $module) {
            foreach ([...self::ACTIONS, ...($module === 'approvals' ? ['approve'] : [])] as $action) {
                $names[] = "$module.$action";
                Permission::updateOrCreate(['name' => "$module.$action", 'guard_name' => 'web'], ['module' => $module, 'action' => $action]);
            }
        }

        foreach (self::ROLES as $name => $def) {
            $role = Role::updateOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                ['display_name' => $def['display'], 'description' => $def['description'], 'is_system' => true],
            );
            $role->syncPermissions($def['permissions'] === ['*'] ? $names : $def['permissions']);
        }
    }
}
