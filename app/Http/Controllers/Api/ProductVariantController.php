<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ProductVariantService;
use App\Exceptions\AppError;
use App\Models\ProductVariant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductVariantController extends Controller
{
    public function __construct(protected ProductVariantService $variantService) {}

    // ── Public Routes ──

    public function byProduct(string $productId): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->variantService->getByProduct($productId)]);
    }

    public function getVariantByAttributes(Request $request, string $productId): JsonResponse
    {
        $query = ProductVariant::where('product_id', $productId);
        if ($request->has('color')) { $query->where('attributes->color', $request->color); }
        if ($request->has('size')) { $query->where('attributes->size', $request->size); }
        if ($request->has('sku')) { $query->where('sku', $request->sku); }
        return response()->json(['success' => true, 'data' => $query->first()]);
    }

    public function show(string $id): JsonResponse
    {
        try {
            return response()->json(['success' => true, 'data' => $this->variantService->getById($id)]);
        } catch (AppError $e) { return $e->render(); }
    }

    // ── Admin Routes ──

    public function store(Request $request, string $productId): JsonResponse
    {
        try {
            $validated = $request->validate(['name' => 'required|string', 'sku' => 'required|string', 'price' => 'nullable|numeric', 'quantity' => 'nullable|integer']);
            return response()->json(['success' => true, 'message' => 'Variant created', 'data' => $this->variantService->create($productId, $validated)], 201);
        } catch (AppError $e) { return $e->render(); }
    }

    public function update(Request $request, string $id): JsonResponse
    {
        try {
            return response()->json(['success' => true, 'data' => $this->variantService->update($id, $request->all())]);
        } catch (AppError $e) { return $e->render(); }
    }

    public function destroy(string $id): JsonResponse
    {
        try {
            $this->variantService->delete($id);
            return response()->json(['success' => true, 'message' => 'Variant deleted']);
        } catch (AppError $e) { return $e->render(); }
    }

    public function lowStock(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->variantService->getLowStock()]);
    }

    public function getAllVariants(Request $request): JsonResponse
    {
        $query = ProductVariant::with('product:id,name');
        if ($request->has('search')) { $s = $request->search; $query->where(function ($q) use ($s) { $q->where('sku', 'like', "%{$s}%")->orWhere('name', 'like', "%{$s}%"); }); }
        return response()->json(['success' => true, 'data' => $query->latest()->paginate($request->input('per_page', 20))]);
    }

    public function bulkCreateVariants(Request $request, string $productId): JsonResponse
    {
        try {
            $validated = $request->validate(['variants' => 'required|array', 'variants.*.name' => 'required|string', 'variants.*.sku' => 'required|string', 'variants.*.price' => 'nullable|numeric', 'variants.*.quantity' => 'nullable|integer']);
            $created = [];
            foreach ($validated['variants'] as $v) {
                $v['product_id'] = $productId;
                $created[] = ProductVariant::create($v);
            }
            return response()->json(['success' => true, 'data' => $created], 201);
        } catch (AppError $e) { return $e->render(); }
    }

    public function updateQuantity(Request $request, string $id): JsonResponse
    {
        try {
            $validated = $request->validate(['quantity' => 'required|integer|min:0']);
            $variant = ProductVariant::findOrFail($id);
            $variant->update(['quantity' => $validated['quantity']]);
            return response()->json(['success' => true, 'data' => $variant]);
        } catch (AppError $e) { return $e->render(); }
    }

    public function bulkUpdateQuantities(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate(['variants' => 'required|array', 'variants.*.id' => 'required|string', 'variants.*.quantity' => 'required|integer|min:0']);
            foreach ($validated['variants'] as $v) {
                ProductVariant::where('id', $v['id'])->update(['quantity' => $v['quantity']]);
            }
            return response()->json(['success' => true, 'message' => 'Quantities updated']);
        } catch (AppError $e) { return $e->render(); }
    }
}
