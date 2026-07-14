<?php

namespace App\Services;

use App\Models\Bundle;

class BundlePasswordAccess
{
    public static function sessionKey(Bundle $bundle): string
    {
        return 'bundle_password.'.$bundle->id;
    }

    public static function requiresPassword(Bundle $bundle): bool
    {
        return ! empty($bundle->password);
    }

    public static function isUnlocked(Bundle $bundle): bool
    {
        if (! self::requiresPassword($bundle)) {
            return true;
        }

        return (bool) session(self::sessionKey($bundle));
    }

    public static function unlock(Bundle $bundle, string $password): bool
    {
        if (! self::requiresPassword($bundle)) {
            return true;
        }

        if (! hash_equals((string) $bundle->password, $password)) {
            return false;
        }

        session([self::sessionKey($bundle) => true]);

        return true;
    }

    public static function forget(Bundle $bundle): void
    {
        session()->forget(self::sessionKey($bundle));
    }
}
