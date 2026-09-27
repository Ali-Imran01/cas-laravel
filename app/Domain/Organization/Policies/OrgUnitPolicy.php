<?php

namespace App\Domain\Organization\Policies;

use App\Domain\Identity\Models\User;

/** Also covers positions: they are managed as part of their unit. */
class OrgUnitPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermissionTo('organization.view');
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermissionTo('organization.create');
    }

    public function update(User $actor): bool
    {
        return $actor->hasPermissionTo('organization.edit');
    }

    public function delete(User $actor): bool
    {
        return $actor->hasPermissionTo('organization.delete');
    }
}
