<?php

namespace App\Services;

use App\Repositories\OrderRepository;
use App\Repositories\CartRepository;
use App\Exceptions\AppError;
use App\Services\SocketService;
use App\Services\NotificationService;
use App\Services\EmailService;
use App\Services\SMSService;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderService
{
    protected array $validTransitions = [
        'PENDING' => ['CONFIRMED', 'CANCELLED'],
        'CONFIRMED' => ['PROCESSING', 'CANCELLED'],
        'PROCESSING' => ['SHIPPED', 'CANCELLED'],
        'SHIPPED' => ['DELIVERED'],
        'DELIVERED' => ['RETURNED', 'RETURN_REQUESTED'],
        'CANCELLED' => [],
        'RETURNED' => [],
        'RETURN_REQUESTED' => ['RETURNED', 'CANCELLED'],
    ];

    public function __construct(
        protected OrderRepository $orderRepository,
        protected CartRepository $cartRepository,
        protected SocketService $socketService,
        protected NotificationService $notificationService,
        protected EmailService $emailService,
        protected SMSService $smsService
    ) {}

    public function createOrder(string $userId, array $data): array
    {
        if (empty($data['items'])) {
            throw AppError::validation('Order must contain at least one item');
        }

        $total = collect($data['items'])->sum(fn($item) => ($item['price'] ?? 0) * ($item['quantity'] ?? 1));
        if ($total <= 0) throw AppError::validation('Invalid order total');

        $orderNumber = 'ORD-' . now()->timestamp . '-' . strtoupper(substr(uniqid(), -4));

        // Resolve default address
        $shippingAddressId = $data['shipping_address_id'];
        if ($shippingAddressId === 'default') {
            $address = $this->orderRepository->getUserDefaultAddress($userId);
            if (!$address) throw AppError::validation('No default shipping address found');
            $shippingAddressId = $address->id;
        }

        // Pre-load variants for all products in the order (avoid N+1)
        $productIds = collect($data['items'])->pluck('product_id')->unique()->toArray();
        $variantsByProduct = ProductVariant::whereIn('product_id', $productIds)
            ->orderBy('quantity', 'desc')
            ->get()
            ->groupBy('product_id');

        // Wrap all DB writes in a transaction for atomicity (matching TS CheckoutService)
        $order = DB::transaction(function () use ($data, $userId, $total, $orderNumber, $shippingAddressId, $variantsByProduct) {
            $order = $this->orderRepository->create([
                'order_number' => $orderNumber,
                'user_id' => $userId,
                'shipping_address_id' => $shippingAddressId,
                'billing_address_id' => $data['billing_address_id'] ?? null,
                'subtotal' => $total,
                'tax' => $data['tax'] ?? 0,
                'shipping_cost' => $data['shipping_cost'] ?? 0,
                'discount' => $data['discount'] ?? 0,
                'total' => $total + ($data['tax'] ?? 0) + ($data['shipping_cost'] ?? 0) - ($data['discount'] ?? 0),
                'status' => ($data['payment_method'] ?? '') === 'COD' ? 'CONFIRMED' : 'PENDING',
                'confirmed_at' => ($data['payment_method'] ?? '') === 'COD' ? now() : null,
                'notes' => $data['notes'] ?? null,
            ]);

            // Build order items and collect stock deductions for batch processing
            $orderItemsData = [];
            $variantDeductions = [];  // [variantId => quantity]
            $productDeductions = [];  // [productId => quantity]

            foreach ($data['items'] as $item) {
                $variantId = null;
                $variants = $variantsByProduct->get($item['product_id']);

                if ($variants && $variants->isNotEmpty()) {
                    // Find variant with sufficient stock
                    $variant = $variants->firstWhere('quantity', '>=', $item['quantity']);
                    if (!$variant) {
                        $totalVariantStock = $variants->sum('quantity');
                        $variantName = $variants->first()->name ?? 'Product';
                        throw AppError::validation(
                            "Insufficient variant stock for \"{$variantName}\". Available across variants: {$totalVariantStock}, requested: {$item['quantity']}"
                        );
                    }
                    $variantId = $variant->id;
                    $variantDeductions[$variant->id] = ($variantDeductions[$variant->id] ?? 0) + $item['quantity'];
                } else {
                    // No variants — fall back to product-level stock
                    $product = Product::where('id', $item['product_id'])->first();
                    if (!$product || $product->quantity < $item['quantity']) {
                        $productName = $product ? $product->name : 'Product';
                        $availableStock = $product ? $product->quantity : 0;
                        throw AppError::validation(
                            "Insufficient stock for \"{$productName}\". Available: {$availableStock}, requested: {$item['quantity']}"
                        );
                    }
                    $productDeductions[$item['product_id']] = ($productDeductions[$item['product_id']] ?? 0) + $item['quantity'];
                }

                $orderItemsData[] = [
                    'order_id' => $order->id,
                    'user_id' => $userId,
                    'product_id' => $item['product_id'],
                    'variant_id' => $variantId,
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                    'total' => ($item['price'] ?? 0) * ($item['quantity'] ?? 1),
                ];
            }

            $this->orderRepository->createOrderItems($orderItemsData);

            // Batch deduct variant stock — single query (parallel equivalent of TS Promise.all)
            if (!empty($variantDeductions)) {
                $cases = [];
                $ids = [];
                foreach ($variantDeductions as $vId => $qty) {
                    $escapedId = str_replace("'", "''", $vId);
                    $cases[] = "WHEN id = '{$escapedId}' THEN quantity - {$qty}";
                    $ids[] = "'{$escapedId}'";
                }
                DB::statement(
                    'UPDATE product_variants SET quantity = CASE ' . implode(' ', $cases) . ' ELSE quantity END WHERE id IN (' . implode(',', $ids) . ')'
                );
            }

            // Batch deduct product stock — single query
            if (!empty($productDeductions)) {
                $cases = [];
                $ids = [];
                foreach ($productDeductions as $pId => $qty) {
                    $escapedId = str_replace("'", "''", $pId);
                    $cases[] = "WHEN id = '{$escapedId}' THEN quantity - {$qty}";
                    $ids[] = "'{$escapedId}'";
                }
                DB::statement(
                    'UPDATE products SET quantity = CASE ' . implode(' ', $cases) . ' ELSE quantity END WHERE id IN (' . implode(',', $ids) . ')'
                );
            }

            // Create payment record if payment method provided
            if (!empty($data['payment_method'])) {
                $this->orderRepository->createPayment([
                    'order_id' => $order->id,
                    'method' => $data['payment_method'],
                    'amount' => $total,
                    'status' => 'PENDING',
                ]);
            }

            // Clear cart within transaction
            $this->cartRepository->clearCart($userId);

            return $order;
        });

        // Send order confirmation email (fire-and-forget, matching TS behavior)
        $this->sendOrderConfirmationEmail($order, $data['items'], $total);

        // Send order confirmation SMS (fire-and-forget)
        $this->sendOrderConfirmationSMS($order, $total);

        // If COD auto-confirmed, also send status update notifications
        if (($data['payment_method'] ?? '') === 'COD') {
            $this->sendOrderStatusUpdateSMS($order, 'CONFIRMED');
            $this->sendOrderStatusUpdateEmail($order, 'CONFIRMED');
        }

        // Emit real-time socket event for admin sidebar badge updates
        try {
            $this->socketService->emitOrderUpdate('order:created', [
                'orderId' => $order->id,
                'orderNumber' => $order->order_number,
                'status' => $order->status,
                'userId' => $userId,
                'timestamp' => now()->toIso8601String(),
                'summary' => ['total' => (float) $total],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to emit order:created socket event', ['error' => $e->getMessage()]);
        }

        return $order->fresh()->load('items.product', 'payment')->toArray();
    }

    public function getOrder(string $orderId): array
    {
        $order = $this->orderRepository->findWithDetails($orderId);
        if (!$order) throw AppError::notFound('Order not found');

        $data = $order->toArray();

        // ── Order-level camelCase mapping ──
        $data['orderNumber']      = $data['order_number'] ?? null;
        $data['createdAt']        = $data['created_at'] ?? null;
        $data['updatedAt']        = $data['updated_at'] ?? null;
        $data['shippingCost']     = $data['shipping_cost'] ?? 0;
        $data['couponId']         = $data['coupon_id'] ?? null;
        $data['adminNotes']       = $data['admin_notes'] ?? null;
        $data['userId']           = $data['user_id'] ?? null;
        $data['totalAmount']      = $data['total'] ?? 0;
        $data['paymentMethod']    = $data['payment_method'] ?? null;

        // Timeline timestamp fields
        $data['confirmedAt']        = $data['confirmed_at'] ?? null;
        $data['processingAt']       = $data['processing_at'] ?? null;
        $data['shippedAt']          = $data['shipped_at'] ?? null;
        $data['deliveredAt']        = $data['delivered_at'] ?? null;
        $data['cancelledAt']        = $data['cancelled_at'] ?? null;
        // These columns may not exist in the orders table yet
        $data['returnRequestedAt']  = $data['return_requested_at'] ?? null;
        $data['returnedAt']         = $data['returned_at'] ?? null;

        // ── Customer name ──
        if ($order->relationLoaded('user') && $order->user) {
            $data['customerName'] = trim(($order->user->first_name ?? '') . ' ' . ($order->user->last_name ?? '')) ?: ($order->user->email ?? 'Guest');
        } else {
            $data['customerName'] = 'Guest';
        }

        // ── User nested fields ──
        if (isset($data['user']) && is_array($data['user'])) {
            $data['user']['firstName']   = $data['user']['first_name'] ?? '';
            $data['user']['lastName']    = $data['user']['last_name'] ?? '';
            $data['user']['phoneNumber'] = $data['user']['phone_number'] ?? null;
            $data['user']['createdAt']   = $data['user']['created_at'] ?? null;
        }

        // ── Payment nested fields ──
        if (isset($data['payment']) && is_array($data['payment'])) {
            $data['payment']['transactionId']   = $data['payment']['transaction_id'] ?? null;
            $data['payment']['gatewayResponse'] = $data['payment']['gateway_response'] ?? null;
        }

        // ── Order Items ──
        if (isset($data['items']) && is_array($data['items'])) {
            foreach ($data['items'] as &$item) {
                $item['variantId']   = $item['variant_id'] ?? null;
                $item['productName'] = $item['product']['name'] ?? $item['name'] ?? 'Product';
                $item['createdAt']   = $item['created_at'] ?? null;
                if (isset($item['product']) && is_array($item['product'])) {
                    $item['product']['createdAt'] = $item['product']['created_at'] ?? null;
                }
            }
            unset($item);
        }

        // ── Shipping Address (toArray() keys use the relationship method name = camelCase) ──
        if (isset($data['shippingAddress']) && is_array($data['shippingAddress'])) {
            $data['shippingAddress'] = $this->mapOrderAddress($data['shippingAddress']);
        }

        // ── Billing Address ──
        if (isset($data['billingAddress']) && is_array($data['billingAddress'])) {
            $data['billingAddress'] = $this->mapOrderAddress($data['billingAddress']);
        }

        // ── Timeline ──
        if (isset($data['timeline']) && is_array($data['timeline'])) {
            foreach ($data['timeline'] as &$entry) {
                $entry['createdAt'] = $entry['created_at'] ?? null;
            }
            unset($entry);
        }

        return $data;
    }

    /**
     * Map snake_case address fields to camelCase.
     */
    private function mapOrderAddress(array $addr): array
    {
        $addr['firstName']    = $addr['first_name'] ?? '';
        $addr['lastName']     = $addr['last_name'] ?? '';
        $addr['phoneNumber']  = $addr['phone_number'] ?? null;
        $addr['addressLine1'] = $addr['address_line1'] ?? '';
        $addr['addressLine2'] = $addr['address_line2'] ?? '';
        $addr['zipCode']      = $addr['zip_code'] ?? '';
        $addr['isDefault']    = $addr['is_default'] ?? false;
        return $addr;
    }

    public function getByOrderNumber(string $orderNumber): array
    {
        $order = $this->orderRepository->findByOrderNumber($orderNumber);
        if (!$order) throw AppError::notFound('Order not found');
        return $order->toArray();
    }

    public function getUserOrders(string $userId, array $filters = []): array
    {
        $paginator = $this->orderRepository->getUserOrders($userId, $filters);
        $result = $paginator->toArray();

        // Map snake_case DB fields to camelCase for frontend
        if (isset($result['data']) && is_array($result['data'])) {
            $result['data'] = array_map(function ($order) {
                $order['createdAt']   = $order['created_at'] ?? null;
                $order['updatedAt']   = $order['updated_at'] ?? null;
                $order['totalAmount'] = $order['total'] ?? 0;
                $order['orderNumber'] = $order['order_number'] ?? null;
                $order['userId']      = $order['user_id'] ?? null;
                return $order;
            }, $result['data']);
        }

        return $result;
    }

    public function getAllOrders(array $filters = []): array
    {
        $paginator = $this->orderRepository->findMany($filters);

        // Transform each order to include camelCase fields matching frontend expectations
        $transformed = collect($paginator->items())->map(function ($order) {
            $data = $order->toArray();
            $data['customerName'] = $order->relationLoaded('user') && $order->user
                ? trim(($order->user->first_name ?? '') . ' ' . ($order->user->last_name ?? '')) ?: ($order->user->email ?? 'Guest')
                : 'Guest';
            $data['createdAt']   = $data['created_at'] ?? null;
            $data['updatedAt']   = $data['updated_at'] ?? null;
            $data['userId']      = $data['user_id'] ?? null;
            $data['totalAmount'] = $data['total'] ?? 0;
            return $data;
        });

        return [
            'orders'     => $transformed->toArray(),
            'pagination' => [
                'page' => $paginator->currentPage(),
                'pages' => $paginator->lastPage(),
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
            ],
        ];
    }

    public function updateStatus(string $orderId, string $newStatus): array
    {
        $order = $this->orderRepository->findByIdOrFail($orderId);

        if (!isset($this->validTransitions[$order->status]) || !in_array($newStatus, $this->validTransitions[$order->status])) {
            throw AppError::validation("Cannot transition from {$order->status} to {$newStatus}");
        }

        $updated = $this->orderRepository->updateStatus($orderId, $newStatus);

        // Send status update SMS (fire-and-forget, matching TS behavior)
        $this->sendOrderStatusUpdateSMS($updated, $newStatus);

        // Send status update email (fire-and-forget, matching TS behavior)
        $this->sendOrderStatusUpdateEmail($updated, $newStatus);

        return $updated->load('items.product')->toArray();
    }

    public function cancelOrder(string $orderId, ?string $reason = null): array
    {
        $order = $this->orderRepository->findByIdOrFail($orderId);

        if (!in_array($order->status, ['PENDING', 'CONFIRMED'])) {
            throw AppError::validation('Only pending or confirmed orders can be cancelled');
        }

        // Restore stock when cancelling — batch restoration
        // Eager load items to avoid N+1 query
        $order->load('items');
        $orderItems = $order->items;
        $variantRestores = [];  // [variantId => quantity]
        $productRestores = [];  // [productId => quantity]

        foreach ($orderItems as $item) {
            if ($item->variant_id) {
                $variantRestores[$item->variant_id] = ($variantRestores[$item->variant_id] ?? 0) + $item->quantity;
            } else {
                $productRestores[$item->product_id] = ($productRestores[$item->product_id] ?? 0) + $item->quantity;
            }
        }

        // Batch restore variant stock — single query
        if (!empty($variantRestores)) {
            $cases = [];
            $ids = [];
            foreach ($variantRestores as $vId => $qty) {
                $escapedId = str_replace("'", "''", $vId);
                $cases[] = "WHEN id = '{$escapedId}' THEN quantity + {$qty}";
                $ids[] = "'{$escapedId}'";
            }
            DB::statement(
                'UPDATE product_variants SET quantity = CASE ' . implode(' ', $cases) . ' ELSE quantity END WHERE id IN (' . implode(',', $ids) . ')'
            );
        }

        // Batch restore product stock — single query
        if (!empty($productRestores)) {
            $cases = [];
            $ids = [];
            foreach ($productRestores as $pId => $qty) {
                $escapedId = str_replace("'", "''", $pId);
                $cases[] = "WHEN id = '{$escapedId}' THEN quantity + {$qty}";
                $ids[] = "'{$escapedId}'";
            }
            DB::statement(
                'UPDATE products SET quantity = CASE ' . implode(' ', $cases) . ' ELSE quantity END WHERE id IN (' . implode(',', $ids) . ')'
            );
        }

        $updated = $this->orderRepository->update($orderId, [
            'status' => 'CANCELLED',
            'notes' => $reason,
        ]);

        return $updated->toArray();
    }

    public function getRevenueStats(?\DateTime $startDate = null, ?\DateTime $endDate = null): array
    {
        return $this->orderRepository->getRevenueStats($startDate, $endDate);
    }

    public function getOrderTracking(string $orderId, string $userId): array
    {
        $order = $this->orderRepository->findWithDetails($orderId);
        if (!$order) throw AppError::notFound('Order not found');

        if ($order->user_id !== $userId) {
            throw AppError::forbidden('You do not have permission to view this order');
        }

        $shipping = $order->shipping;

        return [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'status' => $order->status,
            'estimated_delivery' => $shipping?->estimated_delivery,
            'tracking_number' => $shipping?->tracking_number,
            'carrier' => $shipping?->carrier,
            'timeline' => array_values(array_filter([
                ['status' => 'ORDER_PLACED', 'date' => $order->created_at, 'description' => 'Order placed'],
                ['status' => 'PROCESSING', 'date' => $order->processing_at, 'description' => 'Order being prepared'],
                ['status' => 'SHIPPED', 'date' => $order->shipped_at, 'description' => 'Order shipped'],
                ['status' => 'DELIVERED', 'date' => $order->delivered_at, 'description' => 'Order delivered'],
            ], fn($t) => !is_null($t['date']))),
        ];
    }

    public function getTotalOrdersCount(): int
    {
        return $this->orderRepository->getTotalOrdersCount();
    }

    public function getPublicTracking(string $orderNumber): array
    {
        $order = $this->orderRepository->findByOrderNumber($orderNumber);
        if (!$order) throw AppError::notFound('Order not found');

        $shipping = $order->shipping;

        $items = $order->items->map(function ($item) {
            return [
                'product_id' => $item->product_id,
                'name' => $item->product?->name,
                'quantity' => $item->quantity,
                'price' => (float) $item->price,
                'total' => (float) $item->total,
                'image_url' => $item->product?->images?->first()?->url,
            ];
        })->toArray();

        return [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'status' => $order->status,
            'subtotal' => (float) $order->subtotal,
            'total' => (float) $order->total,
            'estimated_delivery' => $shipping?->estimated_delivery,
            'tracking_number' => $shipping?->tracking_number,
            'carrier' => $shipping?->carrier,
            'items' => $items,
            'timeline' => array_values(array_filter([
                ['status' => 'ORDER_PLACED', 'date' => $order->created_at, 'description' => 'Order placed'],
                ['status' => 'PROCESSING', 'date' => $order->processing_at, 'description' => 'Order being prepared'],
                ['status' => 'SHIPPED', 'date' => $order->shipped_at, 'description' => 'Order shipped'],
                ['status' => 'DELIVERED', 'date' => $order->delivered_at, 'description' => 'Order delivered'],
            ], fn($t) => !is_null($t['date']))),
        ];
    }

    public function subscribeToUpdates(string $orderId, string $userId, array $data): array
    {
        $order = $this->orderRepository->findByIdOrFail($orderId);

        if ($order->user_id !== $userId) {
            throw AppError::forbidden('You do not have permission to modify this order');
        }

        $subscriptionData = [
            'subscribed_at' => now()->toIso8601String(),
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'email_updates' => $data['email_updates'] ?? true,
            'sms_updates' => $data['sms_updates'] ?? false,
        ];

        $this->orderRepository->update($orderId, ['admin_notes' => json_encode($subscriptionData)]);

        return ['order_id' => $orderId, 'subscribed' => true];
    }

    public function requestReturn(string $orderId, string $userId, string $reason, ?array $items = null): array
    {
        $order = $this->orderRepository->findByIdOrFail($orderId);

        if ($order->user_id !== $userId) {
            throw AppError::forbidden('You do not have permission to request a return for this order');
        }

        $daysSinceDelivery = $order->delivered_at
            ? (int) now()->diffInDays($order->delivered_at)
            : 999;

        if ($daysSinceDelivery > 30) {
            throw AppError::validation('Return period expired (30 days)');
        }

        if ($order->status === 'RETURNED') {
            throw AppError::validation('Order already returned');
        }

        if ($order->status === 'RETURN_REQUESTED') {
            throw AppError::validation('Return already requested for this order');
        }

        // Validate items belong to this order
        $orderItems = $order->items()->pluck('product_id')->toArray();
        if ($items !== null) {
            $invalidItems = array_diff($items, $orderItems);
            if (!empty($invalidItems)) {
                throw AppError::validation('Some items do not belong to this order');
            }
        }

        // Create return request record
        $order->returnRequests()->create([
            'user_id' => $userId,
            'reason' => $reason,
            'status' => 'PENDING',
            'description' => $items ? implode(', ', $items) : null,
        ]);

        // Update order status
        $this->orderRepository->updateStatus($orderId, 'RETURN_REQUESTED');

        return ['order_id' => $orderId, 'reason' => $reason, 'status' => 'PENDING', 'message' => 'Return request submitted'];
    }

    /**
     * Send order confirmation email asynchronously (matching TS behavior)
     */
    private function sendOrderConfirmationEmail($order, array $items, float $total): void
    {
        try {
            $emailEnabled = $this->emailService->isEmailEnabled();
            $templateActive = $this->emailService->isTemplateActive('orderConfirmation');
            if (!$emailEnabled || !$templateActive) return;

            $user = \App\Models\User::find($order->user_id);
            if (!$user) return;

            // Get product names
            $productIds = array_column($items, 'product_id');
            $products = Product::whereIn('id', $productIds)->pluck('name', 'id')->toArray();

            $formattedItems = array_map(function ($item) use ($products) {
                return [
                    'name' => $products[$item['product_id']] ?? 'Product',
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                    'total' => ($item['price'] ?? 0) * ($item['quantity'] ?? 1),
                ];
            }, $items);

            $this->emailService->sendOrderConfirmation(
                $user->email,
                $user->first_name . ' ' . $user->last_name,
                [
                    'orderNumber' => $order->order_number,
                    'customerName' => $user->first_name . ' ' . $user->last_name,
                    'items' => $formattedItems,
                    'subtotal' => $total,
                    'shippingCost' => 0,
                    'tax' => 0,
                    'discount' => 0,
                    'total' => $total,
                    'shippingAddress' => 'N/A',
                    'paymentMethod' => 'N/A',
                ]
            );

            // Create in-app notification
            $this->notificationService->create(
                $order->user_id,
                'ORDER',
                'Order Confirmed 🎉',
                "Your order {$order->order_number} has been placed successfully. Total: $" . number_format($total, 2),
                ['orderId' => $order->id, 'orderNumber' => $order->order_number]
            );
        } catch (\Exception $e) {
            Log::error('Failed to send order confirmation email', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Send order confirmation SMS asynchronously (matching TS behavior)
     */
    private function sendOrderConfirmationSMS($order, float $total): void
    {
        try {
            $smsEnabled = $this->smsService->isSmsEnabled();
            if (!$smsEnabled) return;

            $user = \App\Models\User::find($order->user_id);
            if (!$user || !$user->phone_number) return;

            $this->smsService->sendOrderConfirmationSMS(
                $user->phone_number,
                $user->first_name . ' ' . $user->last_name,
                $order->order_number,
                $total
            );
        } catch (\Exception $e) {
            Log::error('Failed to send order confirmation SMS', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Send order status update SMS asynchronously (matching TS behavior)
     */
    private function sendOrderStatusUpdateSMS($order, string $newStatus): void
    {
        try {
            $smsEnabled = $this->smsService->isSmsEnabled();
            if (!$smsEnabled) return;

            $user = \App\Models\User::find($order->user_id);
            if (!$user || !$user->phone_number) return;

            $this->smsService->sendOrderStatusUpdateSMS(
                $user->phone_number,
                $user->first_name . ' ' . $user->last_name,
                $order->order_number,
                $newStatus
            );
        } catch (\Exception $e) {
            Log::error('Failed to send order status update SMS', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Send order status update email asynchronously (matching TS behavior)
     */
    private function sendOrderStatusUpdateEmail($order, string $newStatus): void
    {
        try {
            $emailEnabled = $this->emailService->isEmailEnabled();
            $templateActive = $this->emailService->isTemplateActive('orderStatusUpdate');
            if (!$emailEnabled || !$templateActive) return;

            $user = \App\Models\User::find($order->user_id);
            if (!$user) return;

            $this->emailService->sendOrderStatusUpdate(
                $user->email,
                $user->first_name . ' ' . $user->last_name,
                $order->order_number,
                $newStatus
            );
        } catch (\Exception $e) {
            Log::error('Failed to send order status update email', ['error' => $e->getMessage()]);
        }
    }
}
