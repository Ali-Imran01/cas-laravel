<?php

namespace App\Domain\Identity\Policies;

use App\Domain\Identity\Models\User;

/**
 * Permission checks only. Self-protection and "keep one admin" rules live in the actions, because
 * Gate::before lets super admins through every policy.
 */
class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->checkPermissionTo('users.view');
    }

    public function view(User $actor, User $target): bool
    {
        return $actor->checkPermissionTo('users.view');
    }

    public function create(User $actor): bool
    {
        return $actor->checkPermissionTo('users.create');
    }

    public function update(User $actor, User $target): bool
    {
        return $actor->checkPermissionTo('users.edit') && $this->mayManage($actor, $target);
    }

    public function delete(User $actor, User $target): bool
    {
        return $actor->checkPermissionTo('users.delete') && $this->mayManage($actor, $target);
    }

    // Account state changes share the edit permission.
    public function lock(User $actor, User $target): bool
    {
        return $this->update($actor, $target);
    }

    public function unlock(User $actor, User $target): bool
    {
        return $this->update($actor, $target);
    }

    public function deactivate(User $actor, User $target): bool
    {
        return $this->update($actor, $target);
    }

    public function reactivate(User $actor, User $target): bool
    {
        return $this->update($actor, $target);
    }

    public function transfer(User $actor, User $target): bool
    {
        return $this->update($actor, $target);
    }

    public function invite(User $actor, User $target): bool
    {
        return $this->update($actor, $target);
    }

    /** Only super admins may touch other super admins. */
    private function mayManage(User $actor, User $target): bool
    {
        return ! $target->hasRole('super_admin') || $actor->hasRole('super_admin');
    }
}
