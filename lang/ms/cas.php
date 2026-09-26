<?php

return [
    'auth' => [
        'failed' => 'Maklumat log masuk ini tidak sepadan dengan rekod kami.',
        'locked' => 'Terlalu banyak percubaan gagal. Cuba lagi dalam :minutes minit.',
        'inactive' => 'Akaun ini tidak aktif. Hubungi pentadbir anda.',
        'throttle' => 'Terlalu banyak percubaan. Cuba lagi dalam :seconds saat.',
        'mfa_invalid' => 'Kod tidak sah atau telah tamat tempoh.',
        'mfa_session' => 'Sesi log masuk anda telah tamat. Sila log masuk semula.',
        'mfa_email_wait' => 'Kod baru sahaja dihantar. Tunggu seminit sebelum meminta kod lain.',
        'current_password' => 'Kata laluan semasa tidak betul.',
        'password_reused' => 'Pilih kata laluan yang tidak digunakan baru-baru ini.',
        'reset_sent' => 'Jika e-mel itu berdaftar, pautan set semula telah dihantar.',
        'mfa_enabled' => 'Pengesahan dua langkah dihidupkan.',
        'mfa_disabled' => 'Pengesahan dua langkah dimatikan.',
        'password_changed' => 'Kata laluan dikemas kini.',
    ],
    'mail' => [
        'otp_subject' => 'Kod log masuk anda',
        'otp_line' => 'Kod log masuk anda ialah :code. Kod ini tamat dalam :minutes minit.',
        'otp_ignore' => 'Jika ini bukan anda, tukar kata laluan anda.',
    ],
];
