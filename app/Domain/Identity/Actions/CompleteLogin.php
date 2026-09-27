<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Audit\Audit;
use App\Domain\Audit\Enums\AuthResult;
use App\Domain\Audit\Enums\LoginMethod;
use App\Domain\Identity\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** Starts the real session once every factor has passed. */
class CompleteLogin
{
    /** @param LoginMethod $method how the last factor was passed: password alone, or the MFA step */
    public function __invoke(Request $request, User $user, bool $remember = false, LoginMethod $method = LoginMethod::Password): void
    {
        Auth::guard('web')->login($user, $remember); // fires the Login event
        $request->session()->regenerate();

        $user->forceFill(['last_login_at' => now(), 'last_login_ip' => $request->ip()])->save();

        Audit::loginAttempt($user, $user->staff_id, $method, AuthResult::Success);
    }
}
