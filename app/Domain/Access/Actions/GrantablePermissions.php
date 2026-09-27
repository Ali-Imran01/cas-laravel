<?php

namespace App\Domain\Access\Actions;

use App\Domain\Identity\Models\User;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Permission;

/** Permissions an actor may put into a role: everything for super admins, otherwise only what they hold. */
class GrantablePermissions
{
    /** @return Collection<int, string> */
    public function __invoke(User $actor): Collection
    {
        return $actor->hasRole('super_admin')
            ? Permission::query()->orderBy('name')->pluck('name')
            : $actor->getAllPermissions()->pluck('name')->sort()->values();
    }
}
