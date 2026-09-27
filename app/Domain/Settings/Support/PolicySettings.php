<?php

namespace App\Domain\Settings\Support;

use App\Domain\Settings\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * The security policy knobs an administrator may tune at runtime, layered over the shipped defaults in
 * config/cas.php. Only the ten keys below are ever stored or overridden; every other `cas.auth.*` value
 * (the TOTP issuer name, for instance) stays fixed in the config file.
 */
class PolicySettings
{
    private const CACHE_KEY = 'settings:policies';

    /** @var array<string, array<int, string>> field => validation rules */
    public const FIELDS = [
        'max_attempts' => ['integer', 'min:3', 'max:20'],
        'lockout_minutes' => ['integer', 'min:1', 'max:1440'],
        'login_throttle_per_minute' => ['integer', 'min:3', 'max:60'],
        'password_min_length' => ['integer', 'min:8', 'max:64'],
        'password_history' => ['integer', 'min:0', 'max:24'],
        'password_expiry_days' => ['integer', 'min:0', 'max:365'],
        'mfa_pending_minutes' => ['integer', 'min:2', 'max:60'],
        'mfa_max_attempts' => ['integer', 'min:3', 'max:20'],
        'mfa_email_otp_minutes' => ['integer', 'min:1', 'max:30'],
        'recovery_codes' => ['integer', 'min:4', 'max:20'],
    ];

    /** The effective values right now: stored overrides layered on the shipped defaults.
     *
     * @return array<string, int>
     */
    public function current(): array
    {
        return array_merge(array_intersect_key(config('cas.auth'), self::FIELDS), $this->stored());
    }

    /** @return array<string, int> only the keys an administrator has actually overridden */
    public function stored(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            $setting = Setting::query()->where('key', 'policies')->first();

            return $setting === null ? [] : ($setting->value ?? []);
        });
    }

    /** @param array<string, int> $data */
    public function save(array $data): void
    {
        Setting::query()->updateOrCreate(['key' => 'policies'], ['value' => array_intersect_key($data, self::FIELDS)]);
        Cache::forget(self::CACHE_KEY);
    }

    /** Layers whatever has been overridden onto config(), so every existing config('cas.auth.*') call site picks it up unchanged. */
    public function applyOverrides(): void
    {
        foreach ($this->stored() as $key => $value) {
            if (array_key_exists($key, self::FIELDS)) {
                config(["cas.auth.$key" => $value]);
            }
        }
    }
}
