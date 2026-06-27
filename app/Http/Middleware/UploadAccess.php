<?php

namespace App\Http\Middleware;

use App\Helpers\Upload;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class UploadAccess
{
    /**
     * Handle an incoming request.
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Upload::canUpload($request->ip()) === true) {
            return $next($request);
        }

        if (Auth::check()) {
            return $next($request);
        }

        if ($request->ajax()) {
            abort(401);
        }

        return response()->view('login');
    }
}
