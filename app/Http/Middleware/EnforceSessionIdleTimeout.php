<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnforceSessionIdleTimeout
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $timeoutMinutes = config('security.session_idle_timeout', 60);

        if ($timeoutMinutes > 0 && Auth::check()) {
            $lastActivity = $request->session()->get('last_activity_at');

            if ($lastActivity !== null && now()->diffInMinutes($lastActivity, absolute: true) >= $timeoutMinutes) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                if ($request->expectsJson()) {
                    return response()->json(['message' => __('auth.session-expired')], 401);
                }

                return redirect()->route('login')->with('status', __('auth.session-expired'));
            }

            $request->session()->put('last_activity_at', now());
        }

        return $next($request);
    }
}
