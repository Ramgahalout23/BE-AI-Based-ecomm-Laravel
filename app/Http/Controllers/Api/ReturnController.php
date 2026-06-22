<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\RefundService;
use App\Exceptions\AppError;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReturnController extends Controller
{
    public function __construct(
        protected RefundService $refundService
    ) {}

    /**
     * Create a return request.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'order_id' => 'required|string',
                'reason' => 'required|string|max:255',
                'description' => 'nullable|string',
                'refund_amount' => 'nullable|numeric|min:0',
                'refund_to_wallet' => 'nullable|boolean',
            ]);

            $returnRequest = $this->refundService->createReturnRequest(
                $validated['order_id'],
                Auth::id(),
                $validated
            );

            return response()->json([
                'success' => true,
                'message' => 'Return request created',
                'data' => $returnRequest,
            ], 201);
        } catch (AppError $e) { return $e->render(); }
    }

    /**
     * Get user's return requests.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->refundService->getUserReturnRequests(Auth::id()),
        ]);
    }

    // ── Admin Routes ──

    /**
     * Admin: list all return requests.
     */
    public function adminIndex(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->refundService->getAllReturnRequests($request->all()),
        ]);
    }

    /**
     * Admin: approve return request.
     */
    public function approve(Request $request, string $id): JsonResponse
    {
        try {
            $returnRequest = $this->refundService->approveReturnRequest(
                $id,
                Auth::id(),
                $request->input('admin_response')
            );

            return response()->json([
                'success' => true,
                'message' => 'Return request approved',
                'data' => $returnRequest,
            ]);
        } catch (AppError $e) { return $e->render(); }
    }

    /**
     * Admin: reject return request.
     */
    public function reject(Request $request, string $id): JsonResponse
    {
        try {
            $returnRequest = $this->refundService->rejectReturnRequest(
                $id,
                Auth::id(),
                $request->input('admin_response')
            );

            return response()->json([
                'success' => true,
                'message' => 'Return request rejected',
                'data' => $returnRequest,
            ]);
        } catch (AppError $e) { return $e->render(); }
    }

    /**
     * Admin: complete return and process refund to wallet.
     */
    public function complete(string $id): JsonResponse
    {
        try {
            $returnRequest = $this->refundService->completeReturn($id, Auth::id());

            return response()->json([
                'success' => true,
                'message' => 'Return completed and refund processed',
                'data' => $returnRequest,
            ]);
        } catch (AppError $e) { return $e->render(); }
    }

    /**
     * Admin: list all processed refunds.
     */
    public function allRefunds(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->refundService->getAllRefunds($request->all()),
        ]);
    }
}
