<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Notifications\InviteUser;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

/** Emails a set-your-password link. A new link replaces any earlier one for the same account. */
class SendInvite
{
    /** @throws ValidationException */
    public function __invoke(User $user): void
    {
        if ($user->status !== UserStatus::Pending) {
            throw ValidationException::withMessages(['user' => __('cas.users.not_pending')]);
        }

        $token = Password::broker('invites')->createToken($user);
        $user->notify(new InviteUser($token));
    }
}
