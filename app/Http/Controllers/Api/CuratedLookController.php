<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CuratedLook;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CuratedLookController extends Controller
{
    /**
     * Public: List all active curated looks with their products.
     * GET /api/v1/curated-looks
     */
    public function index(): JsonResponse
    {
        $looks = CuratedLook::with('products.images')
            ->where('is_active', true)
            ->orderBy('display_order')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn ($look) => [
                'id' => $look->id,
                'name' => $look->name,
                'slug' => $look->slug,
                'image_url' => $look->image_url,
                'description' => $look->description,
                'display_order' => $look->display_order,
                'products' => $look->products->map(fn ($p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'slug' => $p->slug,
                    'price' => $p->price,
                    'old_price' => $p->old_price,
                    'image_url' => optional($p->images->first())->url,
                    'rating' => $p->rating,
                ]),
            ]);

        return response()->json([
            'success' => true,
            'data' => $looks,
        ]);
    }

    /**
     * Admin: List all curated looks (including inactive) with products.
     * GET /api/v1/admin/curated-looks
     */
    public function adminIndex(Request $request): JsonResponse
    {
        $query = CuratedLook::with('products.images')->orderBy('display_order')->orderBy('created_at', 'desc');

        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        $looks = $query->paginate($request->per_page ?? 50);

        return response()->json([
            'success' => true,
            'data' => $looks->items(),
            'pagination' => [
                'page' => $looks->currentPage(),
                'pages' => $looks->lastPage(),
                'total' => $looks->total(),
                'per_page' => $looks->perPage(),
            ],
        ]);
    }

    /**
     * Admin: Show a single curated look with its products.
     * GET /api/v1/admin/curated-looks/{id}
     */
    public function show(string $id): JsonResponse
    {
        $look = CuratedLook::with('products.images')->findOrFail($id);
        return response()->json(['success' => true, 'data' => $look]);
    }

    /**
     * Admin: Create a curated look.
     * POST /api/v1/admin/curated-looks
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'image_url' => 'nullable|string|max:2048',
            'description' => 'nullable|string|max:1000',
            'display_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $validated['slug'] = Str::slug($validated['name']) . '-' . Str::random(6);

        $look = CuratedLook::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Curated look created',
            'data' => $look,
        ], 201);
    }

    /**
     * Admin: Update a curated look.
     * PUT /api/v1/admin/curated-looks/{id}
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $look = CuratedLook::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'image_url' => 'nullable|string|max:2048',
            'description' => 'nullable|string|max:1000',
            'display_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        if (isset($validated['name']) && $validated['name'] !== $look->name) {
            $validated['slug'] = Str::slug($validated['name']) . '-' . Str::random(6);
        }

        $look->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Curated look updated',
            'data' => $look->fresh(),
        ]);
    }

    /**
     * Admin: Delete a curated look.
     * DELETE /api/v1/admin/curated-looks/{id}
     */
    public function destroy(string $id): JsonResponse
    {
        $look = CuratedLook::findOrFail($id);
        $look->delete();

        return response()->json([
            'success' => true,
            'message' => 'Curated look deleted',
        ]);
    }

    /**
     * Admin: Reorder curated looks.
     * PATCH /api/v1/admin/curated-looks/reorder
     */
    public function reorder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'looks' => 'required|array|min:1',
            'looks.*.id' => 'required|string|exists:curated_looks,id',
            'looks.*.display_order' => 'required|integer|min:0',
        ]);

        foreach ($validated['looks'] as $item) {
            CuratedLook::where('id', $item['id'])->update(['display_order' => $item['display_order']]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Curated looks reordered',
        ]);
    }

    /**
     * Admin: Sync products for a curated look (attach/detach).
     * POST /api/v1/admin/curated-looks/{id}/products
     * Body: { product_ids: string[] }
     */
    public function syncProducts(Request $request, string $id): JsonResponse
    {
        $look = CuratedLook::findOrFail($id);

        $validated = $request->validate([
            'product_ids' => 'required|array',
            'product_ids.*' => 'string|exists:products,id',
        ]);

        // Sync with display_order based on the order of product_ids
        $syncData = [];
        foreach ($validated['product_ids'] as $index => $productId) {
            $syncData[$productId] = ['display_order' => $index];
        }

        $look->products()->sync($syncData);

        $look->load('products');

        return response()->json([
            'success' => true,
            'message' => 'Products synced',
            'data' => $look,
        ]);
    }
}
