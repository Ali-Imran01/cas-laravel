<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Identity\Actions\ChangePassword;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Rules\NotRecentlyUsed;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password as PasswordBroker;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PasswordResetController extends Controller
{
    public function request(): Response
    {
        return Inertia::render('ForgotPassword');
    }

    public function email(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        PasswordBroker::sendResetLink($request->only('email'));

        // Same answer whether or not the address exists, so it cannot be used to probe accounts.
        return back()->with('status', __('cas.auth.reset_sent'));
    }

    public function edit(Request $request, string $token): Response
    {
        return Inertia::render('ResetPassword', ['token' => $token, 'email' => (string) $request->query('email')]);
    }

    public function update(Request $request, ChangePassword $change): RedirectResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $status = PasswordBroker::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) use ($change) {
                // Policy checks that need the account run here, after the token is known to be valid.
                $rule = new NotRecentlyUsed($user);
                $rule->validate('password', $password, function (string $message) {
                    throw ValidationException::withMessages(['password' => $message]);
                });

                $change($user, $password, null, 'reset'); // keeps no session: every device signs out
            },
        );

        if ($status !== PasswordBroker::PasswordReset) {
            throw ValidationException::withMessages(['email' => __($status)]);
        }

        return redirect()->route('login')->with('status', __('cas.auth.password_changed'));
    }
}
