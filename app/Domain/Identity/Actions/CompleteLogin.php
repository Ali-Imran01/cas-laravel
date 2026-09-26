<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** Starts the real session once every factor has passed. */
class CompleteLogin
{
    public function __invoke(Request $request, User $user, bool $remember = false): void
    {
        Auth::guard('web')->login($user, $remember); // fires the Login event (audit hooks in Phase 3)
        $request->session()->regenerate();

        $user->forceFill(['last_login_at' => now(), 'last_login_ip' => $request->ip()])->save();
    }
}
