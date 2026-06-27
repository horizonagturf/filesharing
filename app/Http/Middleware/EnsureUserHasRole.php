<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (! Auth::check()) {
            abort(403);
        }

        $user = Auth::user();

        if ($user->hasRole(UserRole::Admin)) {
            return $next($request);
        }

        $allowed = array_map(
            fn (string $role) => UserRole::from($role),
            $roles
        );

        if ($user->hasAnyRole(...$allowed)) {
            return $next($request);
        }

        abort(403);
    }
}
