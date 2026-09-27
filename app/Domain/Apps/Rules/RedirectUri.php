<?php

namespace App\Domain\Apps\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Where CAS may send a signed-in user (and their authorization code) back to. Passport matches these
 * exactly, so the only job here is to refuse anything that could leak a code to the wrong place.
 */
class RedirectUri implements ValidationRule
{
    private const LOOPBACK = ['localhost', '127.0.0.1', '[::1]'];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $parts = is_string($value) && strlen($value) <= 255 ? parse_url($value) : false;
        $scheme = is_array($parts) ? strtolower($parts['scheme'] ?? '') : '';
        $host = is_array($parts) ? strtolower($parts['host'] ?? '') : '';

        $valid = $parts !== false
            && $host !== ''
            && ! isset($parts['fragment']) && ! isset($parts['user']) && ! isset($parts['pass'])
            && ! str_contains((string) $value, '*')
            // Plain http only for local development, never for a real host.
            && ($scheme === 'https' || ($scheme === 'http' && in_array($host, self::LOOPBACK, true)));

        if (! $valid) {
            $fail(__('cas.apps.bad_redirect'));
        }
    }
}
