<?php

namespace App\Domain\Identity\Actions\Mfa;

use App\Domain\Identity\Models\User;

class RecoveryCodes
{
    /** @return list<string> */
    public function generate(): array
    {
        return array_map(
            fn () => substr($hex = bin2hex(random_bytes(5)), 0, 5).'-'.substr($hex, 5),
            range(1, config('cas.auth.recovery_codes')),
        );
    }

    /** Single use: a matching code is removed. */
    public function consume(User $user, string $code): bool
    {
        $code = strtolower(trim($code));
        $codes = $user->mfa_recovery_codes ?? [];

        foreach ($codes as $i => $stored) {
            if (hash_equals($stored, $code)) {
                unset($codes[$i]);
                $user->forceFill(['mfa_recovery_codes' => array_values($codes)])->save();

                return true;
            }
        }

        return false;
    }
}
