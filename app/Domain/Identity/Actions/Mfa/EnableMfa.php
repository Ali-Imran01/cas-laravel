<?php

namespace App\Domain\Identity\Actions\Mfa;

use App\Domain\Audit\Audit;
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
        Audit::record('MFA_ENABLE', "Two-step verification turned on for {$user->staff_id}", $user, ['mfa_enabled' => false], ['mfa_enabled' => true], actorId: $user->id);

        return $codes;
    }
}
