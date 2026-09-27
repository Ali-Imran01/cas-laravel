<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Audit\Audit;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Rules\NotRecentlyUsed;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

/** Turns an invited (pending, password-less) account into an active one. */
class AcceptInvite
{
    public function __construct(private readonly ChangePassword $changePassword) {}

    /**
     * @param  array{email: string, token: string, password: string, password_confirmation: string}  $credentials
     *
     * @throws ValidationException
     */
    public function __invoke(array $credentials): void
    {
        $status = Password::broker('invites')->reset($credentials, function (User $user, string $password) {
            if ($user->status !== UserStatus::Pending) {
                throw ValidationException::withMessages(['email' => __('cas.users.not_pending')]);
            }

            (new NotRecentlyUsed($user))->validate('password', $password, function (string $message) {
                throw ValidationException::withMessages(['password' => $message]);
            });

            ($this->changePassword)($user, $password, null, 'invite');
            $user->forceFill(['status' => UserStatus::Active, 'email_verified_at' => now()])->save();
            Audit::record('ACTIVATE', "{$user->staff_id} accepted the invitation and activated the account", $user, ['status' => 'pending'], ['status' => 'active'], actorId: $user->id);
        });

        if ($status !== Password::PasswordReset) {
            throw ValidationException::withMessages(['email' => __($status)]);
        }
    }
}
