<?php

namespace App\Domain\Identity\Actions\Mfa;

use App\Domain\Identity\Models\User;

class EnableMfa
{
    public function __construct(private readonly Totp $totp, private readonly RecoveryCodes $recovery) {}

    /**
     * Turns MFA on once the user proves their authenticator app holds the secret.
     *
     * @return list<string>|null the recovery codes (shown once), or null when the code was wrong
     */
    public function __invoke(User $user, string $secret, string $code): ?array
    {
        if (! $this->totp->verify($secret, $code, $user->id)) {
            return null;
        }

        $codes = $this->recovery->generate();
        $user->forceFill(['mfa_secret' => $secret, 'mfa_enabled' => true, 'mfa_recovery_codes' => $codes])->save();

        return $codes;
    }
}
