<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Identity\Actions\CompleteLogin;
use App\Domain\Identity\Actions\Mfa\EmailOtp;
use App\Domain\Identity\Actions\Mfa\VerifyMfaChallenge;
use App\Domain\Identity\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** Second step of sign-in: the session holds only a pending user id until a factor passes. */
class MfaChallengeController extends Controller
{
    public function create(Request $request): Response|RedirectResponse
    {
        return $this->pendingUser($request) ? Inertia::render('MfaChallenge') : redirect()->route('login');
    }

    public function store(Request $request, VerifyMfaChallenge $verify, CompleteLogin $complete): RedirectResponse
    {
        $user = $this->pendingUser($request);
        if (! $user) {
            return redirect()->route('login')->withErrors(['identifier' => __('cas.auth.mfa_session')]);
        }

        $data = $request->validate([
            'method' => ['required', 'in:totp,recovery,email'],
            'code' => ['required', 'string', 'max:32'],
        ]);

        $key = "mfa:$user->id";
        if (RateLimiter::tooManyAttempts($key, config('cas.auth.mfa_max_attempts'))) {
            // Too many wrong codes: drop the pending sign-in so the password step has to be repeated.
            $request->session()->forget('mfa');

            return redirect()->route('login')->withErrors([
                'identifier' => __('cas.auth.throttle', ['seconds' => RateLimiter::availableIn($key)]),
            ]);
        }

        if (! $verify($user, $data['method'], $data['code'])) {
            RateLimiter::hit($key, 300);
            throw ValidationException::withMessages(['code' => __('cas.auth.mfa_invalid')]);
        }

        RateLimiter::clear($key);
        $remember = (bool) $request->session()->get('mfa.remember');
        $request->session()->forget('mfa');
        $complete($request, $user, $remember);

        return redirect()->intended(route('dashboard'));
    }

    public function sendEmail(Request $request, EmailOtp $email): RedirectResponse
    {
        $user = $this->pendingUser($request);
        if (! $user) {
            return redirect()->route('login')->withErrors(['identifier' => __('cas.auth.mfa_session')]);
        }

        $email->send($user);

        return back()->with('status', 'mfa-email-sent');
    }

    private function pendingUser(Request $request): ?User
    {
        $pending = $request->session()->get('mfa');
        if (! is_array($pending) || ($pending['expires'] ?? 0) < now()->timestamp) {
            $request->session()->forget('mfa');

            return null;
        }

        return User::find($pending['user_id']);
    }
}
