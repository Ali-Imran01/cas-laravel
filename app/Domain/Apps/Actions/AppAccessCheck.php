<?php

namespace App\Domain\Apps\Actions;

use App\Domain\Apps\Enums\AppStatus;
use App\Domain\Apps\Models\Application;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\User;

/** Decides whether a person may sign in to an app right now, and which app role they carry. */
class AppAccessCheck
{
    /** @return string|null null when allowed, otherwise why not: app_disabled | user_inactive | no_access */
    public function denial(User $user, Application $app): ?string
    {
        return match (true) {
            $app->status === AppStatus::Disabled => 'app_disabled',
            $user->status !== UserStatus::Active => 'user_inactive',
            $this->appRole($user, $app) === null => 'no_access',
            default => null,
        };
    }

    /**
     * An individual grant beats a role mapping; if several role mappings apply, the alphabetically first
     * CAS role wins so the answer never depends on row order.
     */
    public function appRole(User $user, Application $app): ?string
    {
        $grant = $app->users()->whereKey($user->id)
            ->where(fn ($q) => $q->whereNull('application_user.expires_at')->orWhere('application_user.expires_at', '>', now()))
            ->first();

        if ($grant) {
            return $grant->getRelation('pivot')->getAttribute('app_role');
        }

        $mapped = $app->roles()->whereIn('roles.id', $user->roles()->select('roles.id'))->orderBy('roles.name')->first();

        return $mapped?->getRelation('pivot')->getAttribute('app_role');
    }
}
