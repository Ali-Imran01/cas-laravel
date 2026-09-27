<?php

namespace App\Domain\Apps\Policies;

use App\Domain\Identity\Models\User;

class ApplicationPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->checkPermissionTo('apps.view');
    }

    public function create(User $actor): bool
    {
        return $actor->checkPermissionTo('apps.create');
    }

    /** Settings, secret rotation, enable/disable and access rules. */
    public function update(User $actor): bool
    {
        return $actor->checkPermissionTo('apps.edit');
    }

    public function delete(User $actor): bool
    {
        return $actor->checkPermissionTo('apps.delete');
    }
}
