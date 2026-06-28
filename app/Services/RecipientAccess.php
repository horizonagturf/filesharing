<?php

namespace App\Services;

use App\Models\Bundle;
use App\Models\BundleRecipient;

class RecipientAccess
{
    public static function sessionKey(Bundle $bundle): string
    {
        return 'recipient_access.'.$bundle->id;
    }

    public static function grant(BundleRecipient $recipient): void
    {
        session([self::sessionKey($recipient->bundle) => strtolower($recipient->email)]);
    }

    public static function emailFor(Bundle $bundle): ?string
    {
        $email = session(self::sessionKey($bundle));

        return is_string($email) ? strtolower($email) : null;
    }

    public static function isVerified(Bundle $bundle, ?string $email = null): bool
    {
        $email = $email ?? self::emailFor($bundle);

        if ($email === null || $email === '') {
            return false;
        }

        return BundleRecipient::query()
            ->where('bundle_id', $bundle->id)
            ->where('email', strtolower($email))
            ->whereNotNull('verified_at')
            ->exists();
    }

    public static function forget(Bundle $bundle): void
    {
        session()->forget(self::sessionKey($bundle));
    }
}
