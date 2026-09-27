<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\User;
use Illuminate\Validation\ValidationException;

/** Refuses any change that would leave the system without an active super admin. */
class EnsureAdminRemains
{
    /** @throws ValidationException */
    public function __invoke(User $target): void
    {
        if (! $target->hasRole('super_admin')) {
            return;
        }

        $others = User::role('super_admin')->where('status', UserStatus::Active->value)->whereKeyNot($target->id)->exists();

        if (! $others) {
            throw ValidationException::withMessages(['user' => __('cas.users.last_admin')]);
        }
    }
}
