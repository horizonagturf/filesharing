<?php

return [

    /*
     * Reverse proxies / load balancers allowed to tell the app the real client
     * IP (X-Forwarded-For) and scheme (X-Forwarded-Proto).
     *
     * Comma-separated IPs or CIDR ranges, e.g. "10.0.0.5" or "10.0.0.0/24".
     * "*" trusts whichever host connects directly; only use it when the app
     * cannot be reached except through the proxy.
     *
     * Leave empty when the app is not behind a proxy. If it IS behind a proxy
     * and this is empty, every user appears to come from the proxy's IP:
     * rate limits are shared by everyone, audit logs record the proxy, and
     * the upload IP allowlist checks the proxy's address.
     */
    'proxies' => env('TRUSTED_PROXIES'),

];
