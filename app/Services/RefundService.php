<?php

namespace App\Services;

use App\Models\Refund;
use App\Models\RefundRequest;
use App\Models\ReturnRequest;
use App\Models\Order;
use App\Exceptions\AppError;
use Illuminate\Support\Facades\DB;

class RefundService
{
    /**
     * Create a refund request for an order item.
     */
    public function createRefundRequest(string $orderId, string $userId, array $data): RefundRequest
    {
        $order = Order::where('id', $orderId)->where('user_id', $userId)->firstOrFail();

        if (!in_array($order->status, ['DELIVERED', 'SHIPPED'])) {
            throw AppError::validation('Refunds are only available for delivered or shipped orders');
        }

        return RefundRequest::create([
            'order_id' => $orderId,
            'user_id' => $userId,
            'reason' => $data['reason'],
            'description' => $data['description'] ?? null,
            'status' => 'PENDING',
        ]);
    }

    /**
     * Create a return request.
     */
    public function createReturnRequest(string $orderId, string $userId, array $data): ReturnRequest
    {
        $order = Order::where('id', $orderId)->where('user_id', $userId)->firstOrFail();

        if (!in_array($order->status, ['DELIVERED', 'SHIPPED'])) {
            throw AppError::validation('Returns are only available for delivered or shipped orders');
        }

        return ReturnRequest::create([
            'order_id' => $orderId,
            'user_id' => $userId,
            'reason' => $data['reason'],
            'description' => $data['description'] ?? null,
            'refund_amount' => $data['refund_amount'] ?? null,
            'refund_to_wallet' => $data['refund_to_wallet'] ?? false,
            'status' => 'PENDING',
        ]);
    }

    /**
     * Get user's refund requests.
     */
    public function getUserRefundRequests(string $userId): array
    {
        return RefundRequest::where('user_id', $userId)
            ->with('order')
            ->latest()
            ->get()
            ->toArray();
    }

    /**
     * Get user's return requests.
     */
    public function getUserReturnRequests(string $userId): array
    {
        return ReturnRequest::where('user_id', $userId)
            ->with('order')
            ->latest()
            ->get()
            ->toArray();
    }

    /**
     * Get user's refunds (processed).
     */
    public function getUserRefunds(string $userId): array
    {
        return Refund::where('user_id', $userId)
            ->with('payment')
            ->latest()
            ->get()
            ->toArray();
    }

    /**
     * Admin: approve a refund request.
     */
    public function approveRefundRequest(string $refundRequestId, string $adminId, ?string $adminResponse = null): RefundRequest
    {
        return DB::transaction(function () use ($refundRequestId, $adminId, $adminResponse) {
            $request = RefundRequest::findOrFail($refundRequestId);

            if ($request->status !== 'PENDING') {
                throw AppError::validation('Refund request has already been processed');
            }

            $request->update([
                'status' => 'APPROVED',
                'admin_response' => $adminResponse,
            ]);

            return $request->fresh();
        });
    }

    /**
     * Admin: reject a refund request.
     */
    public function rejectRefundRequest(string $refundRequestId, string $adminId, ?string $adminResponse = null): RefundRequest
    {
        $request = RefundRequest::findOrFail($refundRequestId);

        if ($request->status !== 'PENDING') {
            throw AppError::validation('Refund request has already been processed');
        }

        $request->update([
            'status' => 'REJECTED',
            'admin_response' => $adminResponse,
        ]);

        return $request->fresh();
    }

    /**
     * Admin: approve a return request and optionally process refund to wallet.
     */
    public function approveReturnRequest(string $returnRequestId, string $adminId, ?string $adminResponse = null): ReturnRequest
    {
        return DB::transaction(function () use ($returnRequestId, $adminId, $adminResponse) {
            $request = ReturnRequest::findOrFail($returnRequestId);

            if ($request->status !== 'PENDING') {
                throw AppError::validation('Return request has already been processed');
            }

            $request->update([
                'status' => 'APPROVED',
                'admin_response' => $adminResponse,
                'processed_at' => now(),
            ]);

            return $request->fresh();
        });
    }

    /**
     * Admin: reject a return request.
     */
    public function rejectReturnRequest(string $returnRequestId, string $adminId, ?string $adminResponse = null): ReturnRequest
    {
        $request = ReturnRequest::findOrFail($returnRequestId);

        if ($request->status !== 'PENDING') {
            throw AppError::validation('Return request has already been processed');
        }

        $request->update([
            'status' => 'REJECTED',
            'admin_response' => $adminResponse,
        ]);

        return $request->fresh();
    }

    /**
     * Admin: complete a return and process refund.
     */
    public function completeReturn(string $returnRequestId, string $adminId): ReturnRequest
    {
        return DB::transaction(function () use ($returnRequestId, $adminId) {
            $request = ReturnRequest::findOrFail($returnRequestId);

            if ($request->status !== 'APPROVED') {
                throw AppError::validation('Return must be approved before completing');
            }

            // If refund to wallet, process it
            if ($request->refund_to_wallet && $request->refund_amount > 0) {
                $walletService = app(WalletService::class);
                $walletService->recharge(
                    $request->user_id,
                    (float) $request->refund_amount,
                    "Refund for return request #{$request->id}",
                    $request->order_id
                );
            }

            $request->update([
                'status' => 'COMPLETED',
                'processed_at' => now(),
            ]);

            return $request->fresh();
        });
    }

    /**
     * Admin: list all refund requests.
     */
    public function getAllRefundRequests(array $filters = []): array
    {
        $query = RefundRequest::with(['user', 'order']);
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        return $query->latest()->paginate($filters['per_page'] ?? 20)->toArray();
    }

    /**
     * Admin: list all return requests.
     */
    public function getAllReturnRequests(array $filters = []): array
    {
        $query = ReturnRequest::with(['user', 'order']);
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        return $query->latest()->paginate($filters['per_page'] ?? 20)->toArray();
    }

    /**
     * Admin: list all processed refunds.
     */
    public function getAllRefunds(array $filters = []): array
    {
        $query = Refund::with(['user', 'payment']);
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        return $query->latest()->paginate($filters['per_page'] ?? 20)->toArray();
    }

    /**
     * Process a refund for a payment (creates Refund record).
     */
    public function processPaymentRefund(string $paymentId, string $userId, float $amount, string $reason): Refund
    {
        return DB::transaction(function () use ($paymentId, $userId, $amount, $reason) {
            $refund = Refund::create([
                'payment_id' => $paymentId,
                'user_id' => $userId,
                'amount' => $amount,
                'reason' => $reason,
                'status' => 'PENDING',
            ]);

            return $refund;
        });
    }
}
