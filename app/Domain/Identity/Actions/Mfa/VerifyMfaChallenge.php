<?php

namespace App\Domain\Identity\Actions\Mfa;

use App\Domain\Identity\Models\User;

class VerifyMfaChallenge
{
    public function __construct(
        private readonly Totp $totp,
        private readonly RecoveryCodes $recovery,
        private readonly EmailOtp $email,
    ) {}

    /** Unknown methods fail closed. */
    public function __invoke(User $user, string $method, string $code): bool
    {
        return match ($method) {
            'totp' => $user->mfa_secret !== null && $this->totp->verify($user->mfa_secret, $code, $user->id),
            'recovery' => $this->recovery->consume($user, $code),
            'email' => $this->email->verify($user, $code),
            default => false,
        };
    }
}
