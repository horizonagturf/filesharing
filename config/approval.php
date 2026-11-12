<?php

return [

    'required_default' => env('APPROVAL_REQUIRED_DEFAULT', false),

    /*
     * Whether bundles uploaded without signing in (only possible when SSO is
     * off and the uploader's IP is allowed) must be approved before sharing.
     */
    'required_anonymous' => env('APPROVAL_REQUIRED_ANONYMOUS', false),

];
