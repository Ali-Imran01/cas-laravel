<?php

namespace App\Domain\Identity\Rules;

use App\Domain\Identity\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Hash;

/** Rejects the current password and the last N (config cas.auth.password_history) ones. */
class NotRecentlyUsed implements ValidationRule
{
    public function __construct(private readonly User $user) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $hashes = $this->user->passwordHistories()->latest('id')->limit(config('cas.auth.password_history'))->pluck('password');

        if ($this->user->password) {
            $hashes->push($this->user->password);
        }

        foreach ($hashes as $hash) {
            if (Hash::check((string) $value, $hash)) {
                $fail(__('cas.auth.password_reused'));

                return;
            }
        }
    }
}
