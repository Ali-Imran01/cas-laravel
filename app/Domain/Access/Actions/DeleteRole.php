<?php

namespace App\Domain\Access\Actions;

use App\Domain\Identity\Models\User;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class DeleteRole
{
    public function __construct(private readonly EnsureRoleManageable $manageable) {}

    /** @throws ValidationException */
    public function __invoke(User $actor, Role $role): void
    {
        ($this->manageable)($actor, $role);

        if ($role->getAttribute('is_system')) {
            throw ValidationException::withMessages(['role' => __('cas.roles.system_role')]);
        }

        if (User::role($role->name)->exists()) {
            throw ValidationException::withMessages(['role' => __('cas.roles.in_use')]);
        }

        $role->delete();
    }
}
