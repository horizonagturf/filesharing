<?php

return [

    'enabled' => env('MICROSOFT_SSO_ENABLED', false),

    'tenant_id' => env('AZURE_TENANT_ID'),

    'allowed_domains' => array_values(array_filter(array_map(
        static fn (string $domain): string => strtolower(trim($domain)),
        explode(',', (string) env('AZURE_ALLOWED_DOMAINS', ''))
    ))),

    'scopes' => ['openid', 'profile', 'email', 'User.Read'],

];
