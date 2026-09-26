<?php

return [
    'auth' => [
        'failed' => 'These credentials do not match our records.',
        'locked' => 'Too many failed attempts. Try again in :minutes minutes.',
        'inactive' => 'This account is not active. Contact your administrator.',
        'throttle' => 'Too many attempts. Try again in :seconds seconds.',
        'mfa_invalid' => 'The code is invalid or has expired.',
        'mfa_session' => 'Your sign-in session expired. Please sign in again.',
        'mfa_email_wait' => 'A code was sent recently. Wait a minute before requesting another.',
        'current_password' => 'The current password is incorrect.',
        'password_reused' => 'Choose a password you have not used recently.',
        'reset_sent' => 'If that email is registered, a reset link is on its way.',
        'mfa_enabled' => 'Two-step verification is on.',
        'mfa_disabled' => 'Two-step verification is off.',
        'password_changed' => 'Password updated.',
    ],
    'mail' => [
        'otp_subject' => 'Your sign-in code',
        'otp_line' => 'Your sign-in code is :code. It expires in :minutes minutes.',
        'otp_ignore' => 'If this was not you, change your password.',
    ],
];
