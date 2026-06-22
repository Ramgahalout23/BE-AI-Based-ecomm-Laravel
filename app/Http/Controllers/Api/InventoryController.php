<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\InventoryService;
use App\Exceptions\AppError;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    public function __construct(protected InventoryService $inventoryService) {}

    public function index(Request $request): JsonResponse
    {
        $result = $this->inventoryService->getAll($request->all());
        return response()->json([
            'success' => true,
            'data' => [
                'items' => $result['data'] ?? [],
                'pagination' => [
                    'page' => $result['current_page'] ?? 1,
                    'pages' => $result['last_page'] ?? 1,
                    'total' => $result['total'] ?? 0,
                    'per_page' => $result['per_page'] ?? 15,
                ],
            ],
        ]);
    }

    public function show(string $productId): JsonResponse
    {
        try {
            return response()->json(['success' => true, 'data' => $this->inventoryService->getByProduct($productId)]);
        } catch (AppError $e) { return $e->render(); }
    }

    public function check(string $productId): JsonResponse
    {
        try {
            $available = $this->inventoryService->checkAvailability($productId);
            return response()->json(['success' => true, 'data' => ['available' => $available]]);
        } catch (AppError $e) { return $e->render(); }
    }

    public function addStock(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate(['product_id' => 'required|string', 'quantity' => 'required|integer|min:1', 'notes' => 'nullable|string']);
            return response()->json(['success' => true, 'message' => 'Stock added', 'data' => $this->inventoryService->addStock($validated['product_id'], $validated['quantity'], $validated['notes'] ?? null)]);
        } catch (AppError $e) { return $e->render(); }
    }

    public function reduceStock(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate(['product_id' => 'required|string', 'quantity' => 'required|integer|min:1', 'notes' => 'nullable|string']);
            return response()->json(['success' => true, 'message' => 'Stock reduced', 'data' => $this->inventoryService->reduceStock($validated['product_id'], $validated['quantity'], $validated['notes'] ?? null)]);
        } catch (AppError $e) { return $e->render(); }
    }

    public function lowStock(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->inventoryService->getLowStock()]);
    }

    public function movement(string $productId): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->inventoryService->getMovement($productId)]);
    }

    /**
     * Get inventory statistics (Admin).
     * GET /api/v1/inventory/stats
     */
    public function stats(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->inventoryService->getStats()]);
    }

    /**
     * Batch update stock (Admin).
     * POST /api/v1/inventory/batch-update
     */
    /**
     * Update stock for a specific inventory item.
     * PATCH /api/v1/admin/inventory/{id}/stock
     */
    public function updateStock(Request $request, string $id): JsonResponse
    {
        try {
            $validated = $request->validate([
                'quantity' => 'required|integer|min:0',
                'notes' => 'nullable|string',
            ]);

            $inventory = \App\Models\Inventory::findOrFail($id);
            $oldQty = (int) $inventory->available_quantity;
            $newQty = (int) $validated['quantity'];
            $diff = $newQty - $oldQty;

            if ($diff > 0) {
                $inventory->increment('total_quantity', $diff);
                $inventory->increment('available_quantity', $diff);
            } elseif ($diff < 0) {
                $reduceBy = abs($diff);
                $inventory->decrement('total_quantity', $reduceBy);
                $inventory->decrement('available_quantity', $reduceBy);
            }

            $inventory->refresh();

            \App\Models\InventoryHistory::create([
                'inventory_id' => $inventory->id,
                'type' => $diff >= 0 ? 'ADD' : 'REMOVE',
                'quantity' => abs($diff),
                'reason' => $validated['notes'] ?: 'Manual stock adjustment via admin',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Stock updated',
                'data' => $inventory->load('product'),
            ]);
        } catch (AppError $e) { return $e->render(); } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function batchUpdate(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'updates' => 'required|array|min:1',
                'updates.*.product_id' => 'required|string',
                'updates.*.quantity' => 'required|integer',
                'updates.*.type' => 'required|string|in:add,reduce',
            ]);
            $results = [];
            foreach ($validated['updates'] as $update) {
                try {
                    if ($update['type'] === 'add') {
                        $results[] = $this->inventoryService->addStock($update['product_id'], abs($update['quantity']));
                    } else {
                        $results[] = $this->inventoryService->reduceStock($update['product_id'], abs($update['quantity']));
                    }
                } catch (\Exception $e) {
                    $results[] = ['product_id' => $update['product_id'], 'error' => $e->getMessage()];
                }
            }
            return response()->json(['success' => true, 'data' => ['updated_count' => count($results), 'results' => $results]]);
        } catch (AppError $e) { return $e->render(); }
    }
}
