<?php

namespace App\Http\Controllers;

use App\Models\Bundle;
use App\Models\BundleRecipient;
use App\Services\BundleInvitationService;
use App\Services\RecipientAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

class InvitationController extends Controller
{
    public function __construct(
        private readonly BundleInvitationService $invitationService,
    ) {}

    public function show(Bundle $bundle, BundleRecipient $recipient): View|RedirectResponse
    {
        abort_unless($recipient->bundle_id === $bundle->id, 404);
        abort_unless($bundle->isShareable(), 404);

        if (RecipientAccess::isVerified($bundle, $recipient->email)) {
            RecipientAccess::grant($recipient);

            return redirect()->route('bundle.preview', ['bundle' => $bundle]);
        }

        if (! $this->invitationService->requiresOtp($bundle)) {
            $this->invitationService->grantAccessWithoutOtp($recipient);

            return redirect()->route('bundle.preview', ['bundle' => $bundle]);
        }

        return view('invitation.show', [
            'bundle' => $bundle,
            'recipient' => $recipient,
            'otpRequestUrl' => $this->invitationService->otpRequestUrl($recipient),
            'otpVerifyUrl' => $this->invitationService->otpVerifyUrl($recipient),
        ]);
    }

    public function requestOtp(Request $request, Bundle $bundle, BundleRecipient $recipient)
    {
        abort_unless($recipient->bundle_id === $bundle->id, 404);
        abort_unless($bundle->isShareable(), 404);
        abort_if(! $this->invitationService->requiresOtp($bundle), 404);

        try {
            $this->invitationService->requestOtp($recipient);

            if ($request->expectsJson()) {
                return response()->json(['message' => __('invitation.otp-sent')]);
            }

            return back()->with('status', __('invitation.otp-sent'));
        } catch (TooManyRequestsHttpException $e) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 429);
            }

            return back()->withErrors(['otp' => $e->getMessage()]);
        } catch (InvalidArgumentException $e) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return back()->withErrors(['otp' => $e->getMessage()]);
        }
    }

    public function verifyOtp(Request $request, Bundle $bundle, BundleRecipient $recipient): RedirectResponse
    {
        abort_unless($recipient->bundle_id === $bundle->id, 404);
        abort_unless($bundle->isShareable(), 404);
        abort_if(! $this->invitationService->requiresOtp($bundle), 404);

        $request->validate([
            'code' => 'required|string|size:6',
        ]);

        try {
            $this->invitationService->verifyOtp($recipient, $request->input('code'));

            return redirect()->route('bundle.preview', ['bundle' => $bundle]);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['code' => $e->getMessage()]);
        }
    }
}
