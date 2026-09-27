<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Audit\Audit;
use App\Domain\Identity\Models\User;
use Illuminate\Support\Facades\DB;

/** Single place a password is set: records history, clears forced-change/lockout, revokes other sessions. */
class ChangePassword
{
    /**
     * @param  string|null  $keepSessionId  session that survives (the one making the change); null revokes all
     * @param  string  $via  change | reset | invite, recorded in the audit entry
     */
    public function __invoke(User $user, string $plain, ?string $keepSessionId = null, string $via = 'change'): void
    {
        DB::transaction(function () use ($user, $plain, $keepSessionId, $via) {
            $user->forceFill([
                'password' => $plain, // hashed by the model cast
                'password_changed_at' => now(),
                'must_change_password' => false,
                'failed_login_count' => 0,
                'locked_until' => null,
            ])->save();

            $user->passwordHistories()->create(['password' => $user->password]);

            $keep = $user->passwordHistories()->latest('id')->limit(config('cas.auth.password_history'))->pluck('id');
            $user->passwordHistories()->whereNotIn('id', $keep)->delete();

            DB::table('sessions')
                ->where('user_id', $user->id)
                ->when($keepSessionId, fn ($q) => $q->where('id', '!=', $keepSessionId))
                ->delete();

            // The person is always the actor here: even a reset or invitation is them proving control of the account.
            Audit::record('PASSWORD_CHANGE', "Password set for {$user->staff_id}", $user, new: ['via' => $via], actorId: $user->id);
        });
    }
}
