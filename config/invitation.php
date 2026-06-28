<?php

return [
    'default_share_mode' => env('DEFAULT_SHARE_MODE', 'invitation'),

    'otp_expiry_minutes' => (int) env('OTP_EXPIRY_MINUTES', 15),

    'otp_max_attempts' => (int) env('OTP_MAX_ATTEMPTS', 5),

    'otp_rate_limit_per_hour' => (int) env('OTP_RATE_LIMIT_PER_HOUR', 5),

    'invitation_link_days' => (int) env('INVITATION_LINK_DAYS', 30),
];
