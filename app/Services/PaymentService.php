<?php

namespace App\Services;

use App\Repositories\PaymentRepository;
use App\Exceptions\AppError;
use App\Models\Order;

class PaymentService
{
    public function __construct(
        protected PaymentRepository $paymentRepository
    ) {}

    /**
     * Get available payment methods (from settings/dynamic - matching TS behavior).
     */
    public function getPaymentMethods(): array
    {
        // Try to load from settings (matching TS dynamic payment methods from DB)
        try {
            $settingsService = app(SettingsService::class);
            $enabledMethods = $settingsService->get('payment_methods', []);
            
            if (!empty($enabledMethods) && is_array($enabledMethods)) {
                return $enabledMethods;
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('Failed to load payment methods from settings', ['error' => $e->getMessage()]);
        }
        
        // Default fallback to hardcoded methods (matching TS behavior)
        return [
            ['id' => 'RAZORPAY', 'name' => 'Razorpay / Cards / UPI', 'description' => 'Popular Indian payment gateway supporting UPI, Netbanking & Cards'],
            ['id' => 'COD', 'name' => 'Cash on Delivery (COD)', 'description' => 'Pay with cash upon delivery of your package'],
        ];
    }

    /**
     * Initiate a payment with validation.
     */
    public function initiatePayment(string $userId, array $data): array
    {
        if (empty($data['order_id'])) throw AppError::validation('Order ID is required');
        if (empty($data['method'])) throw AppError::validation('Payment method is required');
        if (empty($data['amount']) || $data['amount'] <= 0) throw AppError::validation('Amount must be greater than 0');

        // Verify order exists and belongs to user
        $order = Order::find($data['order_id']);
        if (!$order) throw AppError::notFound('Order not found');
        if ($order->user_id !== $userId) throw AppError::validation('Order does not belong to this user');

        // Check for existing completed payment
        $existing = $this->paymentRepository->getPaymentByOrderId($data['order_id']);
        if ($existing && $existing->status === 'COMPLETED') {
            throw AppError::conflict('Payment already completed for this order');
        }

        $payment = $this->paymentRepository->createPayment([
            'order_id' => $data['order_id'],
            'method' => $data['method'],
            'amount' => $data['amount'],
            'status' => 'PENDING',
        ]);

        return $payment->fresh()->toArray();
    }

    /**
     * Verify payment completion.
     */
    public function verifyPayment(string $paymentId, string $transactionId): array
    {
        if (empty($paymentId)) throw AppError::validation('Payment ID is required');
        if (empty($transactionId)) throw AppError::validation('Transaction ID is required');

        $payment = $this->paymentRepository->getPaymentById($paymentId);
        if (!$payment) throw AppError::notFound('Payment not found');
        if ($payment->status === 'COMPLETED') throw AppError::conflict('Payment already verified');

        $updated = $this->paymentRepository->updatePaymentStatus($paymentId, 'COMPLETED', $transactionId);

        // Update order status to CONFIRMED
        if ($payment->order_id) {
            Order::where('id', $payment->order_id)->update(['status' => 'CONFIRMED']);
        }

        return $updated->toArray();
    }

    /**
     * Process a refund.
     */
    public function processRefund(string $userId, array $data): array
    {
        if (empty($data['payment_id'])) throw AppError::validation('Payment ID is required');

        $payment = $this->paymentRepository->getPaymentById($data['payment_id']);
        if (!$payment) throw AppError::notFound('Payment not found');

        // Verify user owns this payment
        $order = Order::find($payment->order_id);
        if (!$order || $order->user_id !== $userId) {
            throw AppError::validation('Unauthorized to refund this payment');
        }

        if ($payment->status !== 'COMPLETED') {
            throw AppError::validation('Can only refund completed payments');
        }

        $refundAmount = $data['amount'] ?? $payment->amount;
        if ($refundAmount > $payment->amount) {
            throw AppError::validation('Refund amount cannot exceed payment amount');
        }

        $refund = $this->paymentRepository->createRefund([
            'payment_id' => $data['payment_id'],
            'user_id' => $userId,
            'amount' => $refundAmount,
            'reason' => $data['reason'] ?? null,
            'status' => 'PENDING',
        ]);

        return $refund->fresh()->load('payment')->toArray();
    }

    /**
     * Get user payments with pagination.
     */
    public function getUserPayments(string $userId, int $page = 1, int $limit = 20): array
    {
        if ($page < 1) throw AppError::validation('Page must be greater than 0');
        if ($limit < 1 || $limit > 100) throw AppError::validation('Limit must be between 1 and 100');

        return $this->paymentRepository->getUserPayments($userId, $page, $limit);
    }

    /**
     * Get payment by order.
     */
    public function getByOrder(string $orderId): array
    {
        $payment = $this->paymentRepository->findByOrder($orderId);
        if (!$payment) throw AppError::notFound('Payment not found');
        return $payment->toArray();
    }

    /**
     * Get payment details with authorization.
     */
    public function getPaymentDetails(string $userId, string $paymentId): array
    {
        if (empty($paymentId)) throw AppError::validation('Payment ID is required');

        $payment = $this->paymentRepository->getPaymentById($paymentId);
        if (!$payment) throw AppError::notFound('Payment not found');

        // Verify authorization
        $order = Order::find($payment->order_id);
        if (!$order || $order->user_id !== $userId) {
            throw AppError::validation('Unauthorized to view this payment');
        }

        return $payment->toArray();
    }

    /**
     * Confirm a payment (legacy alias for verifyPayment).
     */
    public function confirm(string $paymentId, string $transactionId): array
    {
        return $this->verifyPayment($paymentId, $transactionId);
    }

    /**
     * Create a payment intent (legacy).
     */
    public function createPaymentIntent(array $data): array
    {
        $payment = $this->paymentRepository->createPaymentIntent([
            'order_id' => $data['order_id'],
            'method' => $data['method'],
            'amount' => $data['amount'],
            'status' => 'PENDING',
        ]);
        return $payment->toArray();
    }

    /**
     * Get all payments (admin) with pagination and optional status filter.
     */
    public function getAllPayments(int $page = 1, int $limit = 20, ?string $status = null): array
    {
        if ($page < 1) throw AppError::validation('Page must be greater than 0');
        if ($limit < 1 || $limit > 100) throw AppError::validation('Limit must be between 1 and 100');

        return $this->paymentRepository->getAllPayments($page, $limit, $status);
    }

    /**
     * Get payment statistics (admin).
     */
    public function getPaymentStats(?string $startDate = null, ?string $endDate = null): array
    {
        return $this->paymentRepository->getPaymentStats($startDate, $endDate);
    }

    /**
     * Get user refunds.
     */
    public function getUserRefunds(string $userId): array
    {
        return $this->paymentRepository->getUserRefunds($userId)->toArray();
    }

    /**
     * Approve refund (admin).
     */
    public function approveRefund(string $refundId): array
    {
        if (empty($refundId)) throw AppError::validation('Refund ID is required');

        $refund = $this->paymentRepository->getRefundById($refundId);
        if (!$refund) throw AppError::notFound('Refund not found');

        if (in_array($refund->status, ['APPROVED', 'COMPLETED'])) {
            throw AppError::conflict('Refund already processed');
        }

        $updated = $this->paymentRepository->updateRefundStatus($refundId, 'APPROVED');
        return $updated->fresh()->load('payment')->toArray();
    }

    /**
     * Reject refund (admin).
     */
    public function rejectRefund(string $refundId): array
    {
        if (empty($refundId)) throw AppError::validation('Refund ID is required');

        $refund = $this->paymentRepository->getRefundById($refundId);
        if (!$refund) throw AppError::notFound('Refund not found');

        $updated = $this->paymentRepository->updateRefundStatus($refundId, 'REJECTED');
        return $updated->fresh()->load('payment')->toArray();
    }
}
