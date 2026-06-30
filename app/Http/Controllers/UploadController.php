<?php

namespace App\Http\Controllers;

use App\Enums\ShareMode;
use App\Helpers\Upload;
use App\Http\Resources\BundleResource;
use App\Http\Resources\FileResource;
use App\Models\Bundle;
use App\Models\BundleRecipient;
use App\Models\File;
use App\Services\BundleApprovalService;
use App\Services\BundleInvitationService;
use App\Services\OtpPolicy;
use App\Services\ShareModePolicy;
use App\Services\SharingSettings;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

class UploadController extends Controller
{
    public function __construct(
        private readonly BundleApprovalService $approvalService,
        private readonly BundleInvitationService $invitationService,
        private readonly ShareModePolicy $shareModePolicy,
        private readonly OtpPolicy $otpPolicy,
        private readonly SharingSettings $sharingSettings,
    ) {}

    public function createBundle(Request $request, Bundle $bundle)
    {
        $bundle->load('recipients');

        return view('upload', [
            'bundle' => new BundleResource($bundle),
            'baseUrl' => config('app.url'),
            'invitationMode' => $this->invitationService->usesInvitationMode($bundle),
            'canUseStaticLink' => $this->shareModePolicy->canUseStaticLinks(Auth::user()),
            'canChooseOtp' => $this->otpPolicy->canChooseOtpSetting(Auth::user()),
            'defaultRequireOtp' => $this->otpPolicy->defaultRequireOtp(),
            'blockedExtensions' => $this->sharingSettings->blockedExtensions(),
        ]);
    }

    // The upload form
    public function storeBundle(Request $request, Bundle $bundle)
    {
        $this->approvalService->assertEditable($bundle);

        $request->validate([
            'recipients' => 'nullable|array',
            'recipients.*' => 'email',
            'share_mode' => 'nullable|in:invitation,static_link',
            'require_otp' => 'nullable|boolean',
        ]);

        $requested = $request->input('share_mode');

        try {
            if ($requested === null) {
                $shareMode = $this->shareModePolicy->effectiveShareMode(Auth::user(), $bundle->share_mode);
            } else {
                $shareMode = $this->shareModePolicy->resolveShareMode(Auth::user(), $requested);
            }

            $requireOtp = $shareMode === ShareMode::StaticLink
                ? true
                : $this->otpPolicy->resolveRequireOtp(
                    Auth::user(),
                    $request->has('require_otp') ? $request->boolean('require_otp') : null,
                );

            $bundle->update([
                'expiry' => $request->expiry ?? null,
                'password' => $request->password ?? null,
                'title' => $request->title ?? null,
                'description' => $request->description ?? null,
                'max_downloads' => $request->max_downloads ?? 0,
                'share_mode' => $shareMode,
                'require_otp' => $requireOtp,
            ]);

            if ($request->has('recipients') && $this->invitationService->usesInvitationMode($bundle)) {
                $this->invitationService->syncRecipients($bundle, $request->input('recipients', []));
            }

            return response()->json(new BundleResource($bundle->fresh(['recipients'])));
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'result' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (Exception $e) {
            report($e);

            return response()->json([
                'result' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function uploadFile(Request $request, Bundle $bundle)
    {
        $this->approvalService->assertEditable($bundle);

        // Validating form data
        $request->validate([
            'uuid' => 'required|uuid',
            'file' => 'required|file|max:'.(Upload::fileMaxSize() / 1000),
        ]);

        // Generating the file name
        $original = $request->file->getClientOriginalName();
        $blocked = $this->sharingSettings->blockedExtensions();

        if (Upload::isBlockedFilename($original, $blocked)) {
            return response()->json([
                'result' => false,
                'message' => __('app.file-type-blocked'),
            ], 422);
        }

        $filename = substr(sha1($original.time()), 0, mt_rand(20, 30));

        // Moving file to final destination
        try {
            $size = $request->file->getSize();
            if (config('sharing.upload_prevent_duplicates', true) === true && $size < Upload::humanReadableToBytes(config('sharing.hash_maxfilesize', '1G'))) {
                $hash = sha1_file($request->file->getPathname());

                $existing = $bundle->files->whereNotNull('hash')->where('hash', $hash)->count();
                if (! empty($existing) && $existing > 0) {
                    throw new Exception(__('app.duplicate-file'));
                }
            }

            $fullpath = $request->file('file')->storeAs(
                $bundle->slug, $filename, 'uploads'
            );

            if ($fullpath === false) {
                throw new Exception('An error occurred while storing the file');
            }

            // Generating file metadata
            $file = new File([
                'uuid' => $request->uuid,
                'bundle_id' => $bundle->id,
                'original' => $original,
                'filesize' => $size,
                'fullpath' => $fullpath,
                'filename' => $filename,
                'status' => true,
                'hash' => $hash ?? null,
            ]);
            $file->save();

            return response()->json(new FileResource($file));
        } catch (Exception $e) {
            report($e);

            return response()->json([
                'result' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function deleteFile(Request $request, Bundle $bundle)
    {
        $this->approvalService->assertEditable($bundle);

        $request->validate([
            'uuid' => 'required|uuid',
        ]);

        $file = File::where('uuid', $request->uuid)
            ->where('bundle_id', $bundle->id)
            ->firstOrFail();

        try {
            // Physically deleting the file
            if (! Storage::disk('uploads')->delete($file->fullpath)) {
                throw new Exception('Cannot delete file from disk');
            }

            // Destroying the model
            $file->delete();

            return response()->json(new BundleResource($bundle));
        } catch (Exception $e) {
            report($e);

            return response()->json([
                'result' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function completeBundle(Request $request, Bundle $bundle)
    {
        $user = Auth::user();

        if ($bundle->user_id !== null && $user === null) {
            abort(403);
        }

        try {
            $bundle = $this->approvalService->complete($bundle, $user);

            return response()->json(new BundleResource($bundle));
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'result' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (Exception $e) {
            report($e);

            return response()->json([
                'result' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * In this method, we do not delete files
     * physically to spare some time and ressources.
     * We invalidate the expiry date and let the CRON
     * task do the hard work
     */
    public function deleteBundle(Request $request, Bundle $bundle)
    {

        try {
            // Forcing bundle to expire
            $bundle->expires_at = now()->subDays(30);
            $bundle->save();

            // Then deleting file models
            foreach ($bundle->files as $f) {
                $f->delete();
            }

            // Finally deleting bundle
            $bundle->delete();

            return response()->json([
                'success' => true,
            ]);
        } catch (Exception $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function resendInvitation(Request $request, Bundle $bundle, BundleRecipient $recipient)
    {
        abort_unless($recipient->bundle_id === $bundle->id, 404);

        try {
            $this->invitationService->resendInvitation($recipient);

            return response()->json(['message' => __('invitation.invitation-resent')]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function revokeInvitation(Request $request, Bundle $bundle, BundleRecipient $recipient)
    {
        abort_unless($recipient->bundle_id === $bundle->id, 404);

        try {
            $this->invitationService->revokeInvitation($recipient);

            return response()->json([
                'message' => __('invitation.invitation-revoked'),
                'revoked_at' => $recipient->fresh()->revoked_at,
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
