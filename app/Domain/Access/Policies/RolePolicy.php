<?php

namespace App\Domain\Access\Policies;

use App\Domain\Identity\Models\User;

/** Permission checks only; role-level rules (fixed super_admin, no escalation) live in the actions. */
class RolePolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermissionTo('roles.view');
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermissionTo('roles.create');
    }

    public function update(User $actor): bool
    {
        return $actor->hasPermissionTo('roles.edit');
    }

    public function delete(User $actor): bool
    {
        return $actor->hasPermissionTo('roles.delete');
    }
}
