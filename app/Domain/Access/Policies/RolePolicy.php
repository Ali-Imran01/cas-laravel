<?php

namespace App\Domain\Access\Policies;

use App\Domain\Identity\Models\User;

/** Permission checks only; role-level rules (fixed super_admin, no escalation) live in the actions. */
class RolePolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->checkPermissionTo('roles.view');
    }

    public function create(User $actor): bool
    {
        return $actor->checkPermissionTo('roles.create');
    }

    public function update(User $actor): bool
    {
        return $actor->checkPermissionTo('roles.edit');
    }

    public function delete(User $actor): bool
    {
        return $actor->checkPermissionTo('roles.delete');
    }
}
