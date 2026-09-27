<?php

namespace App\Domain\Access\Actions;

use App\Domain\Identity\Models\User;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class SyncRolePermissions
{
    public function __construct(private readonly EnsureRoleManageable $manageable, private readonly GrantablePermissions $grantable) {}

    /**
     * @param  list<string>  $names
     *
     * @throws ValidationException
     */
    public function __invoke(User $actor, Role $role, array $names): void
    {
        ($this->manageable)($actor, $role);

        if (array_diff($names, ($this->grantable)($actor)->all()) !== []) {
            throw ValidationException::withMessages(['permissions' => __('cas.roles.cannot_grant')]);
        }

        $role->syncPermissions($names); // also flushes the permission cache
    }
}
