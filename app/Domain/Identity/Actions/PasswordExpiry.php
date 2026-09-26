<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Models\User;

class PasswordExpiry
{
    public static function mustChange(User $user): bool
    {
        if ($user->must_change_password) {
            return true;
        }

        $days = (int) config('cas.auth.password_expiry_days');

        return $days > 0 && $user->password_changed_at !== null && $user->password_changed_at->addDays($days)->isPast();
    }
}
