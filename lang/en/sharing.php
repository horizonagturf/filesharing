<?php

return [
    'share-mode' => 'Share mode',
    'share-mode-invitation' => 'Invitation + OTP',
    'share-mode-invitation-help' => 'Recipients verify their email before accessing files (recommended).',
    'share-mode-static-link' => 'Static link',
    'share-mode-static-link-help' => 'Anyone with the link can access files — less secure.',
    'static-link-warning' => 'Less secure — the link alone grants access. Only use for trusted recipients.',
    'static-link-not-allowed' => 'Your group is not permitted to use static links. Contact an administrator.',
    'require-otp' => 'Require email verification (OTP)',
    'require-otp-help' => 'When unchecked, recipients access files via their signed invitation link only.',
    'require-otp-enabled-info' => 'Each recipient receives a unique signed invitation link and must enter a one-time verification code sent to their email before accessing files. Add at least one recipient email before sending.',
    'require-otp-disabled-warning' => 'Less secure — the signed invitation link alone grants access with no email verification step. Recipients are optional; you can copy and share the download links yourself after upload. Only use for trusted recipients.',
    'otp-skip-not-allowed' => 'Your group is not permitted to disable OTP for invitation bundles. Contact an administrator.',
    'default-share-mode' => 'Default share mode',
];
