<?php

namespace App\Domain\Organization\Policies;

use App\Domain\Identity\Models\User;

/** Also covers positions: they are managed as part of their unit. */
class OrgUnitPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->checkPermissionTo('organization.view');
    }

    public function create(User $actor): bool
    {
        return $actor->checkPermissionTo('organization.create');
    }

    public function update(User $actor): bool
    {
        return $actor->checkPermissionTo('organization.edit');
    }

    public function delete(User $actor): bool
    {
        return $actor->checkPermissionTo('organization.delete');
    }
}
