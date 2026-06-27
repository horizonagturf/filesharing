<?php

namespace App\Http\Controllers;

use App\Models\ApprovalRequest;
use App\Services\BundleApprovalService;
use Illuminate\Http\Request;
use InvalidArgumentException;

class ApprovalController extends Controller
{
    public function __construct(
        private readonly BundleApprovalService $approvalService,
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

        $approvalRequest->load(['bundle.files', 'bundle.user', 'requester']);

        return view('approval.show', [
            'approvalRequest' => $approvalRequest,
        ]);
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
