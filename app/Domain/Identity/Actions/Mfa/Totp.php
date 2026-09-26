<?php

namespace App\Domain\Identity\Actions\Mfa;

use Illuminate\Support\Facades\Cache;
use PragmaRX\Google2FA\Google2FA;

class Totp
{
    public function __construct(private readonly Google2FA $google2fa) {}

    public function generateSecret(): string
    {
        return $this->google2fa->generateSecretKey(32);
    }

    public function uri(string $account, string $secret): string
    {
        return $this->google2fa->getQRCodeUrl(config('cas.auth.issuer'), $account, $secret);
    }

    /** Accepts one step of clock drift either way and refuses to accept the same code twice. */
    public function verify(string $secret, string $code, int $userId): bool
    {
        $code = preg_replace('/\s+/', '', $code) ?? '';

        if (! preg_match('/^\d{6}$/', $code) || ! $this->google2fa->verifyKey($secret, $code, 1)) {
            return false;
        }

        return Cache::add("totp-used:$userId:$code", true, 90);
    }
}
