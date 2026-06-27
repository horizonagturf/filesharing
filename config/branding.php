<?php

use App\Services\BrandingSettings;

return [

    'show_credit' => filter_var(env('BRANDING_SHOW_CREDIT', true), FILTER_VALIDATE_BOOLEAN),

    'defaults' => [
        BrandingSettings::KEY_APP_NAME => null,
        BrandingSettings::KEY_LOGO_PATH => null,
        BrandingSettings::KEY_PRIMARY_COLOR => '#7e22ce',
        BrandingSettings::KEY_ACCENT_COLOR => '#9333ea',
        BrandingSettings::KEY_FOOTER_TEXT => null,
        BrandingSettings::KEY_TOS_URL => null,
        BrandingSettings::KEY_PRIVACY_URL => null,
        BrandingSettings::KEY_SHOW_CREDIT => null,
    ],

];
