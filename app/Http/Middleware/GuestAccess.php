<?php

namespace App\Http\Middleware;

use App\Models\Bundle;
use App\Services\BundleInvitationService;
use App\Services\RecipientAccess;
use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class GuestAccess
{
    public function __construct(
        private readonly BundleInvitationService $invitationService,
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_if(empty($request->route()->parameter('bundle')), 404);
        $bundle = $request->route()->parameters()['bundle'];
        abort_if(! is_a($bundle, Bundle::class), 404);

        abort_unless($bundle->isShareable(), 404);

        if (! empty($bundle->expires_at)) {
            abort_if($bundle->expires_at->isBefore(Carbon::now()), 404);
        }

        abort_if(($bundle->max_downloads ?? 0) > 0 && $bundle->downloads >= $bundle->max_downloads, 404);

        if ($this->invitationService->usesInvitationMode($bundle)) {
            abort_unless(RecipientAccess::isVerified($bundle), 403);

            return $next($request);
        }

        abort_if(empty($request->auth), 403);
        abort_if($bundle->preview_token !== $request->auth, 403);

        return $next($request);
    }
}
