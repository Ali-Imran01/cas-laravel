<?php

// Security policy defaults. Phase 6 moves the editable ones into the settings table / UI.
return [
    'sso' => [
        // Scopes an app may be allowed to request, with the wording shown in the app settings.
        'scopes' => [
            'openid' => 'Sign in and identify the user',
            'profile' => 'Name, staff ID, organization unit and app role',
            'email' => 'Email address',
            'org.read' => 'Read the organization structure',
            'users.read' => 'Read the staff directory',
            'approvals' => 'Submit approval requests',
        ],
        'default_scopes' => ['openid', 'profile', 'email'],
        // The address apps know CAS by (the `iss` of ID tokens). Set CAS_ISSUER in production; it must be https.
        'issuer' => env('CAS_ISSUER', env('APP_URL', 'http://localhost')),
        'id_token_minutes' => 10,
        'access_token_minutes' => 15, // short-lived; apps renew with the refresh token
        'refresh_token_days' => 30,
        'max_redirect_uris' => 10,
    ],
    'api' => [
        'rate_limit' => 120, // requests per minute, per app and person
    ],
    'import' => [
        'max_kb' => 2048,
        'max_rows' => 5000,
        'chunk' => 100, // rows per progress update
        'max_errors' => 1000, // stored per import; the rest are only counted
    ],
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
