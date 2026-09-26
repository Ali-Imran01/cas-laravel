<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Models\User;
use Illuminate\Support\Facades\DB;

/** Single place a password is set: records history, clears forced-change/lockout, revokes other sessions. */
class ChangePassword
{
    /** @param string|null $keepSessionId session that survives (the one making the change); null revokes all */
    public function __invoke(User $user, string $plain, ?string $keepSessionId = null): void
    {
        DB::transaction(function () use ($user, $plain, $keepSessionId) {
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
        });
    }
}
