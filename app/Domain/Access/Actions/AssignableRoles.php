<?php

namespace App\Domain\Access\Actions;

use App\Domain\Identity\Models\User;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Role;

/**
 * Roles an actor may hand out. Super admins can grant anything; everyone else only roles whose
 * permissions they already hold (no privilege escalation), and never super_admin.
 */
class AssignableRoles
{
    /** @return Collection<int, Role> */
    public function __invoke(User $actor): Collection
    {
        $roles = Role::query()->with('permissions')->orderBy('name')->get();

        if ($actor->hasRole('super_admin')) {
            return $roles;
        }

        $held = $actor->getAllPermissions()->pluck('name');

        return $roles
            ->reject(fn (Role $role) => $role->name === 'super_admin')
            ->filter(fn (Role $role) => $role->permissions->pluck('name')->diff($held)->isEmpty())
            ->values();
    }
}
