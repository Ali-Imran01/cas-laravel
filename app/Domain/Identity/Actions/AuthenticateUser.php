<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Checks staff ID / email + password and applies the account lockout policy.
 * Does not log anyone in: the caller decides between MFA and a full session.
 */
class AuthenticateUser
{
    private static ?string $dummyHash = null;

    /** @throws ValidationException */
    public function __invoke(string $identifier, string $password): User
    {
        $needle = Str::lower(trim($identifier));

        $user = User::query()
            ->where(fn ($q) => $q->whereRaw('lower(staff_id) = ?', [$needle])->orWhereRaw('lower(email) = ?', [$needle]))
            ->first();

        if (! $user) {
            // Same cost as a real check, so response time does not reveal whether the account exists.
            Hash::check($password, self::$dummyHash ??= Hash::make(Str::random(32)));
            throw $this->fail('failed');
        }

        if ($user->locked_until?->isFuture()) {
            throw $this->fail('locked', ['minutes' => (int) ceil(now()->diffInMinutes($user->locked_until, true))]);
        }

        if (! $user->password || ! Hash::check($password, $user->password)) {
            $this->recordFailure($user, $identifier);
            throw $this->fail('failed');
        }

        // Only revealed after a correct password, so status does not leak to guessers.
        if ($user->status !== UserStatus::Active) {
            throw $this->fail('inactive');
        }

        $user->forceFill(['failed_login_count' => 0, 'locked_until' => null])->save();

        return $user;
    }

    private function recordFailure(User $user, string $identifier): void
    {
        event(new Failed('web', $user, ['identifier' => $identifier]));

        $user->increment('failed_login_count');

        if ($user->failed_login_count >= config('cas.auth.max_attempts')) {
            $user->forceFill([
                'failed_login_count' => 0,
                'locked_until' => now()->addMinutes(config('cas.auth.lockout_minutes')),
            ])->save();
            event(new Lockout(request()));
        }
    }

    /** @param array<string, int|string> $replace */
    private function fail(string $key, array $replace = []): ValidationException
    {
        return ValidationException::withMessages(['identifier' => __("cas.auth.$key", $replace)]);
    }
}
