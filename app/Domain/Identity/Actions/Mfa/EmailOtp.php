<?php

namespace App\Domain\Identity\Actions\Mfa;

use App\Domain\Identity\Models\User;
use App\Domain\Identity\Notifications\MfaCode;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class EmailOtp
{
    /** @throws ValidationException when a code was already sent in the last minute */
    public function send(User $user): void
    {
        if (! RateLimiter::attempt("mfa-otp-send:$user->id", 1, fn () => true, 60)) {
            throw ValidationException::withMessages(['code' => __('cas.auth.mfa_email_wait')]);
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        Cache::put("mfa-otp:$user->id", Hash::make($code), now()->addMinutes(config('cas.auth.mfa_email_otp_minutes')));

        $user->notify(new MfaCode($code));
    }

    public function verify(User $user, string $code): bool
    {
        $hash = Cache::get("mfa-otp:$user->id");

        if (! is_string($hash) || ! Hash::check(trim($code), $hash)) {
            return false;
        }

        Cache::forget("mfa-otp:$user->id");

        return true;
    }
}
