<?php

// Security policy defaults. Phase 6 moves the editable ones into the settings table / UI.
return [
    'auth' => [
        'max_attempts' => 5,
        'lockout_minutes' => 15,
        'login_throttle_per_minute' => 10,
        'password_min_length' => 12,
        'password_history' => 5,
        'password_expiry_days' => 90, // 0 disables expiry
        'mfa_pending_minutes' => 10,
        'mfa_max_attempts' => 5,
        'mfa_email_otp_minutes' => 10,
        'recovery_codes' => 8,
        'issuer' => env('APP_NAME', 'CAS'),
    ],
];
