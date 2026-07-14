<?php

namespace App\Services;

use App\Models\Bundle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BundleOwnerAccess
{
    public static function tokenFromRequest(Request $request): ?string
    {
        $header = $request->header('X-Upload-Auth');
        if (is_string($header) && $header !== '') {
            return $header;
        }

        $auth = $request->input('auth', $request->query('auth'));

        return is_string($auth) && $auth !== '' ? $auth : null;
    }

    public static function isOwner(Request $request, Bundle $bundle): bool
    {
        if ((bool) $request->attributes->get('bundle_owner_access')) {
            return true;
        }

        if (Auth::check()
            && $bundle->user_id !== null
            && Auth::id() === $bundle->user_id) {
            return true;
        }

        $token = self::tokenFromRequest($request);
        if ($token === null || $bundle->owner_token === null || $bundle->owner_token === '') {
            return false;
        }

        return hash_equals((string) $bundle->owner_token, $token);
    }
}
