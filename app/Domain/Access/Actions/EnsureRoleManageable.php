<?php

namespace App\Domain\Access\Actions;

use App\Domain\Identity\Models\User;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

/**
 * The super_admin role is fixed, and nobody edits a role that reaches beyond their own permissions.
 * Lives in the actions because Gate::before lets super admins through every policy.
 */
class EnsureRoleManageable
{
    public function __construct(private readonly GrantablePermissions $grantable) {}

    /** @throws ValidationException */
    public function __invoke(User $actor, Role $role): void
    {
        if ($role->name === 'super_admin') {
            throw ValidationException::withMessages(['role' => __('cas.roles.super_admin_fixed')]);
        }

        if ($role->permissions->pluck('name')->diff(($this->grantable)($actor))->isNotEmpty()) {
            throw ValidationException::withMessages(['role' => __('cas.roles.above_you')]);
        }
    }
}
