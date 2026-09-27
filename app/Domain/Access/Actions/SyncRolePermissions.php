<?php

namespace App\Domain\Access\Actions;

use App\Domain\Audit\Audit;
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

        $before = $role->permissions->pluck('name')->sort()->values()->all();
        $role->syncPermissions($names); // also flushes the permission cache
        $after = collect($names)->sort()->values()->all();

        if ($before !== $after) {
            Audit::record('PERMISSIONS', "Changed permissions of role {$role->name}", $role, ['permissions' => $before], ['permissions' => $after]);
        }
    }
}
