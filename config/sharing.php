<?php

use App\Services\SharingSettings;

return [

    'max_filesize' => env('UPLOAD_MAX_FILESIZE', '5M'),
    'max_files' => env('UPLOAD_MAX_FILES', 100),
    'expiry_values' => [
        '1H' => 'one-hour',
        '2H' => 'two-hours',
        '6H' => 'six-hours',
        '12H' => 'twelve-hours',
        '24H' => 'one-day',
        '48H' => 'two-days',
        '1W' => 'one-week',
        '2W' => 'two-weeks',
        '1M' => 'one-month',
        '3M' => 'three-months',
        '6M' => 'six-months',
    ],
    'default_expiry' => 86400, // 1 Day,

    /**
     ** Whether uploaders may choose "forever" (no expiry). Set to false to
     ** force every share to expire within the longest value above.
     */
    'allow_forever' => (bool) env('SHARING_ALLOW_FOREVER', true),

    /**
     ** Cleanup of bundles that never get shared (fs:bundle:purge).
     ** Drafts: never completed, no activity for this many hours.
     ** Denied: denied by a reviewer and not resubmitted for this many days.
     ** Set either to 0 to keep those bundles forever.
     */
    'draft_retention_hours' => (int) env('DRAFT_RETENTION_HOURS', 72),
    'denied_retention_days' => (int) env('DENIED_RETENTION_DAYS', 14),

    /**
     ** IP v4 access limitations
     ** Acceptable formats :
     **  1. Full IP address (192.168.10.2)
     **  2. Wildcard format (192.168.10.*)
     **  3. CIDR Format (192.168.10/24) OR  1.2.3.4/255.255.255.0
     **  4. Start-end IP (192.168.10.0-192.168.10.10)
     */
    'upload_ip_limit' => env('UPLOAD_LIMIT_IPS', null),

    'download_limit_rate' => env('LIMIT_DOWNLOAD_RATE', false),

    'upload_prevent_duplicates' => env('UPLOAD_PREVENT_DUPLICATES', true),

    /**
     ** Max filesize hash processing
     ** TODO: find the best value to avoid too long time processing
     */
    'hash_maxfilesize' => env('HASH_MAX_FILESIZE', '1G'),

    'default_share_mode' => env('DEFAULT_SHARE_MODE', 'invitation'),

    // Enforce OTP: when true, only groups with allow_invitation_without_otp may skip it.
    'invitation_require_otp' => env('INVITATION_REQUIRE_OTP', true),

    // Default OTP to on for new bundles when OTP is not enforced (users may still untick it).
    'invitation_otp_default' => env('INVITATION_OTP_DEFAULT', true),

    'upload_blocked_extensions' => env(
        'UPLOAD_BLOCKED_EXTENSIONS',
        SharingSettings::DEFAULT_BLOCKED_EXTENSIONS,
    ),

    'settings_defaults' => [
        SharingSettings::KEY_DEFAULT_SHARE_MODE => null,
        SharingSettings::KEY_BLOCKED_EXTENSIONS => null,
        SharingSettings::KEY_INVITATION_REQUIRE_OTP => null,
        SharingSettings::KEY_INVITATION_OTP_DEFAULT => null,
    ],
];
