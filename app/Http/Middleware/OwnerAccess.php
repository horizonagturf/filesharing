<?php

namespace App\Http\Middleware;

use App\Models\Bundle;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class OwnerAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Aborting if request is not AJAX
        abort_if(! $request->ajax(), 403);

        // Aborting if Bundle ID is not present
        abort_if(empty($request->route()->parameter('bundle')), 403);
        $bundle = $request->route()->parameters()['bundle'];
        abort_if(! is_a($bundle, Bundle::class), 404);

        // Aborting if auth is not present
        $auth = null;
        if (! empty($request->header('X-Upload-Auth'))) {
            $auth = $request->header('X-Upload-Auth');
        } elseif (! empty($request->auth)) {
            $auth = $request->auth;
        }
        // Aborting if no auth token provided
        abort_if(empty($auth), 403);

        // Aborting if owner token is wrong
        abort_if(! hash_equals((string) $bundle->owner_token, (string) $auth), 403);

        return $next($request);
    }
}
