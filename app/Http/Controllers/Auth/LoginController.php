<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Audit\Audit;
use App\Domain\Audit\Enums\AuthResult;
use App\Domain\Audit\Enums\LoginMethod;
use App\Domain\Identity\Actions\AuthenticateUser;
use App\Domain\Identity\Actions\CompleteLogin;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class LoginController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Login');
    }

    public function store(Request $request, AuthenticateUser $authenticate, CompleteLogin $complete): RedirectResponse
    {
        $data = $request->validate([
            'identifier' => ['required', 'string', 'max:191'],
            'password' => ['required', 'string', 'max:255'],
        ]);

        $key = Str::lower($data['identifier']).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, config('cas.auth.login_throttle_per_minute'))) {
            Audit::loginAttempt(null, $data['identifier'], LoginMethod::Password, AuthResult::Blocked, 'throttled');

            throw ValidationException::withMessages([
                'identifier' => __('cas.auth.throttle', ['seconds' => RateLimiter::availableIn($key)]),
            ]);
        }

        try {
            $user = $authenticate($data['identifier'], $data['password']);
        } catch (ValidationException $e) {
            RateLimiter::hit($key, 60);
            throw $e;
        }
        RateLimiter::clear($key);

        if ($user->mfa_enabled) {
            $request->session()->put('mfa', [
                'user_id' => $user->id,
                'remember' => $request->boolean('remember'),
                'expires' => now()->addMinutes(config('cas.auth.mfa_pending_minutes'))->timestamp,
            ]);

            return redirect()->route('mfa.challenge');
        }

        $complete($request, $user, $request->boolean('remember'));

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Audit::record('LOGOUT', "{$request->user()->staff_id} signed out", $request->user());

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
