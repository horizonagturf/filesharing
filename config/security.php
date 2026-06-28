<?php

return [

    'oauth_rate_limit_per_minute' => (int) env('OAUTH_RATE_LIMIT_PER_MINUTE', 10),

    'download_rate_limit_per_minute' => (int) env('DOWNLOAD_RATE_LIMIT_PER_MINUTE', 30),

    'otp_route_rate_limit_per_hour' => (int) env('OTP_ROUTE_RATE_LIMIT_PER_HOUR', 30),

    'session_idle_timeout' => (int) env('SESSION_IDLE_TIMEOUT', 60),

    'csp' => env('SECURITY_CSP', "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; font-src 'self' data:; connect-src 'self'; frame-ancestors 'none'"),

];
