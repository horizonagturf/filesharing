<?php

namespace App\Http\Controllers;

use App\Enums\AuditEvent;
use App\Models\ApprovalRequest;
use App\Models\File;
use App\Services\Audit;
use App\Services\BundleApprovalService;
use App\Services\ImageThumbnailService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ApprovalController extends Controller
{
    public function __construct(
        private readonly BundleApprovalService $approvalService,
        private readonly ImageThumbnailService $thumbnailService,
    ) {}

    public function index()
    {
        $requests = ApprovalRequest::query()
            ->with(['bundle.files', 'bundle.user', 'requester'])
            ->where('status', 'pending')
            ->orderBy('created_at')
            ->get();

        return view('approval.index', [
            'requests' => $requests,
        ]);
    }

    public function show(ApprovalRequest $approvalRequest)
    {
        abort_unless($approvalRequest->isPending(), 404);

        $approvalRequest->load(['bundle.files', 'bundle.recipients', 'bundle.user', 'requester']);

        return view('approval.show', [
            'approvalRequest' => $approvalRequest,
        ]);
    }

    public function thumbnail(ApprovalRequest $approvalRequest, File $file): Response
    {
        abort_unless($approvalRequest->isPending(), 404);
        abort_unless($file->bundle_id === $approvalRequest->bundle_id, 404);

        return $this->thumbnailService->streamResponse($file);
    }

    public function downloadFile(ApprovalRequest $approvalRequest, File $file): StreamedResponse
    {
        abort_unless($approvalRequest->isPending(), 404);
        abort_unless($file->bundle_id === $approvalRequest->bundle_id, 404);

        $path = config('filesystems.disks.uploads.root').'/'.$file->fullpath;
        abort_unless(file_exists($path), 404);

        $bundle = $approvalRequest->bundle;

        Audit::log(AuditEvent::FileDownloaded, [
            'bundle' => $bundle,
            'file' => $file,
            'user' => request()->user(),
            'metadata' => [
                'context' => 'approval_review',
            ],
        ]);

        $safeName = str_replace(['/', '\\'], '_', $file->original);
        $fallback = str_replace(['/', '\\'], '_', Str::ascii($safeName) ?: 'download.bin');

        $headers = [
            'Content-Type' => 'application/octet-stream',
            'Content-Disposition' => HeaderUtils::makeDisposition(
                HeaderUtils::DISPOSITION_ATTACHMENT,
                $safeName,
                $fallback
            ),
            'Cache-Control' => 'no-cache, must-revalidate',
            'Content-Length' => (string) filesize($path),
        ];

        return response()->stream(function () use ($path) {
            readfile($path);
        }, 200, $headers);
    }

    public function approve(Request $request, ApprovalRequest $approvalRequest)
    {
        abort_unless($request->ajax(), 403);

        try {
            $this->approvalService->approve($approvalRequest, $request->user());

            return response()->json(['result' => true]);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'result' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function deny(Request $request, ApprovalRequest $approvalRequest)
    {
        abort_unless($request->ajax(), 403);

        $validated = $request->validate([
            'reason' => 'required|string|min:3|max:1000',
        ]);

        try {
            $this->approvalService->deny(
                $approvalRequest,
                $request->user(),
                $validated['reason'],
            );

            return response()->json(['result' => true]);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'result' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
