<?php

namespace App\Support;

/**
 * Generates the unguessable identifiers that protect bundles.
 *
 * Everything here comes from random_bytes() (a CSPRNG). Do not use uniqid(),
 * time(), rand() or sha1() of predictable input for these values: slugs and
 * tokens are the only thing standing between the internet and a bundle.
 */
class BundleTokens
{
    /** 160 bits, lowercase hex: safe for URLs and directory names. */
    public static function slug(): string
    {
        return bin2hex(random_bytes(20));
    }

    /** 128 bits, lowercase hex: owner and preview tokens. */
    public static function token(): string
    {
        return bin2hex(random_bytes(16));
    }

    /** Name for an uploaded file on disk. */
    public static function storageFilename(): string
    {
        return bin2hex(random_bytes(16));
    }
}
