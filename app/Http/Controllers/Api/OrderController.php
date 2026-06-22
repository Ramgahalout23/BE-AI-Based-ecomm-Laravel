<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\OrderService;
use App\Exceptions\AppError;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OrderController extends Controller
{
    public function __construct(protected OrderService $orderService) {}

    public function index(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->orderService->getUserOrders(Auth::id(), request()->all())]);
    }

    public function show(string $id): JsonResponse
    {
        try {
            $order = $this->orderService->getOrder($id);
            // Ownership check: users can only see their own orders; admins can see all
            $user = Auth::user();
            if ($user->role === 'CUSTOMER' && isset($order['user_id']) && $order['user_id'] !== $user->id) {
                return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
            }
            return response()->json(['success' => true, 'data' => $order]);
        } catch (AppError $e) { return $e->render(); }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'shipping_address_id' => 'required|string',
                'billing_address_id' => 'nullable|string',
                'items' => 'required|array|min:1',
                'items.*.product_id' => 'required|string',
                'items.*.quantity' => 'required|integer|min:1',
                'items.*.price' => 'required|numeric',
                'shipping_cost' => 'nullable|numeric',
                'discount' => 'nullable|numeric',
                'payment_method' => 'nullable|string',
                'notes' => 'nullable|string',
            ]);

            $order = $this->orderService->createOrder(Auth::id(), $validated);
            return response()->json(['success' => true, 'message' => 'Order created', 'data' => $order], 201);
        } catch (AppError $e) { return $e->render(); }
    }

    public function cancel(string $id, Request $request): JsonResponse
    {
        try {
            $order = $this->orderService->cancelOrder($id, $request->reason);
            return response()->json(['success' => true, 'message' => 'Order cancelled', 'data' => $order]);
        } catch (AppError $e) { return $e->render(); }
    }

    public function updateStatus(Request $request, string $id): JsonResponse
    {
        try {
            $validated = $request->validate(['status' => 'required|string']);
            $order = $this->orderService->updateStatus($id, $validated['status']);
            return response()->json(['success' => true, 'message' => 'Status updated', 'data' => $order]);
        } catch (AppError $e) { return $e->render(); }
    }

    public function tracking(string $orderNumber): JsonResponse
    {
        try {
            $order = $this->orderService->getByOrderNumber($orderNumber);
            return response()->json(['success' => true, 'data' => $order]);
        } catch (AppError $e) { return $e->render(); }
    }

    public function allOrders(Request $request): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->orderService->getAllOrders($request->all())]);
    }

    public function getRevenueStats(Request $request): JsonResponse
    {
        $startDate = $request->start_date ? new \DateTime($request->start_date) : null;
        $endDate = $request->end_date ? new \DateTime($request->end_date) : null;
        $stats = $this->orderService->getRevenueStats($startDate, $endDate);
        return response()->json(['success' => true, 'data' => $stats]);
    }

    public function getOrderTracking(string $id, Request $request): JsonResponse
    {
        try {
            $userId = Auth::id();
            $tracking = $this->orderService->getOrderTracking($id, $userId);
            return response()->json(['success' => true, 'data' => $tracking]);
        } catch (AppError $e) { return $e->render(); }
    }

    public function subscribeToUpdates(string $id, Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'email' => 'nullable|email',
                'phone' => 'nullable|string',
                'email_updates' => 'nullable|boolean',
                'sms_updates' => 'nullable|boolean',
            ]);

            $result = $this->orderService->subscribeToUpdates($id, Auth::id(), $validated);
            return response()->json(['success' => true, 'message' => 'Subscribed to order updates', 'data' => $result]);
        } catch (AppError $e) { return $e->render(); }
    }

    public function requestReturn(string $id, Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'reason' => 'required|string',
                'items' => 'nullable|array',
            ]);

            $result = $this->orderService->requestReturn($id, Auth::id(), $validated['reason'], $validated['items'] ?? null);
            return response()->json(['success' => true, 'message' => 'Return request submitted', 'data' => $result], 201);
        } catch (AppError $e) { return $e->render(); }
    }
}
