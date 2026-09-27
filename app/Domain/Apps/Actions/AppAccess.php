<?php

namespace App\Domain\Apps\Actions;

use App\Domain\Apps\Models\Application;
use App\Domain\Audit\Audit;
use App\Domain\Identity\Models\User;
use Carbon\CarbonInterface;
use Spatie\Permission\Models\Role;

/**
 * Who may sign in to an app, and with which app role. Two routes to access: a whole CAS role is mapped
 * to an app role, or a single person is granted one (optionally until a date).
 */
class AppAccess
{
    public function mapRole(Application $app, Role $role, string $appRole): void
    {
        $before = $app->roles()->whereKey($role->id)->first()?->getRelation('pivot')->getAttribute('app_role');
        $app->roles()->syncWithoutDetaching([$role->id => ['app_role' => $appRole]]);

        Audit::record('ACCESS_GRANT', "Mapped role {$role->name} to app role $appRole in {$app->code}", $app, $before ? ['role' => $role->name, 'app_role' => $before] : [], ['role' => $role->name, 'app_role' => $appRole]);
    }

    public function unmapRole(Application $app, Role $role): void
    {
        $app->roles()->detach($role->id);

        Audit::record('ACCESS_REVOKE', "Removed role {$role->name} from {$app->code}", $app, ['role' => $role->name]);
    }

    public function grantUser(User $actor, Application $app, User $user, string $appRole, ?CarbonInterface $expiresAt): void
    {
        $app->users()->syncWithoutDetaching([$user->id => [
            'app_role' => $appRole, 'granted_by' => $actor->id, 'expires_at' => $expiresAt, 'created_at' => now(),
        ]]);

        Audit::record('ACCESS_GRANT', "Granted {$user->staff_id} app role $appRole in {$app->code}", $app, [], [
            'staff_id' => $user->staff_id, 'app_role' => $appRole, 'expires_at' => $expiresAt?->toIso8601String(),
        ]);
    }

    public function revokeUser(Application $app, User $user): void
    {
        $app->users()->detach($user->id);

        Audit::record('ACCESS_REVOKE', "Removed {$user->staff_id} from {$app->code}", $app, ['staff_id' => $user->staff_id]);
    }
}
