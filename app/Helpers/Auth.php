<?php

namespace App\Helpers;

use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class Auth
{
    public static function isLogged(): bool
    {
        // Checking credentials auth
        if (session()->get('authenticated', false) === true && session()->has('username')) {
            // If user still exists
            try {
                self::getUserDetails(session()->get('username'));

                return true;
            } catch (Exception $e) {
            }
        }

        return false;
    }

    public static function loginUser(string $username, string $password): bool
    {
        try {
            // Checking user existence
            $user = self::getUserDetails($username);

            // Checking password
            if (Hash::check($password, $user->password) !== true) {
                throw new Exception('Invalid password');
            }

            // OK, user's credentials are OK
            session()->put('username', $username);
            session()->put('authenticated', true);

            $user->connected_at = Carbon::now();
            $user->save();

            return true;
        } catch (Exception $e) {
            throw $e;
        }
    }

    public static function getLoggedUserDetails(): User
    {
        if (self::isLogged()) {
            return self::getUserDetails(session()->get('username'));
        }
        throw new UnauthenticatedUser('User is not logged in');
    }

    public static function getUserDetails(string $username): User
    {
        $user = User::find($username);
        if (empty($user)) {
            throw new Exception('No such user');
        }

        return $user;
    }

    public static function setUserDetails(string $username, array $data): array
    {
        $original = self::getUserDetails($username);
        $updated = array_merge($original, $data);

        if (Storage::disk('users')->put($username.'.json', json_encode($updated))) {
            return $updated;
        }

        throw new Exception('Could not update user\'s details');
    }

    public static function logout()
    {
        if (self::isLogged()) {
            session()->invalidate();
        }
    }
}

class UnauthenticatedUser extends Exception {}
