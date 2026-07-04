<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Reel;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReelController extends Controller
{
    /**
     * Public: List all active reels for homepage display.
     * GET /api/v1/reels
     */
    public function index(): JsonResponse
    {
        // Master toggle: check if reels section is enabled
        $reelsEnabled = Setting::where('key', 'reelsEnabled')->value('value');
        if ($reelsEnabled === 'false' || $reelsEnabled === '0') {
            return response()->json([
                'success' => true,
                'data' => [],
            ]);
        }

        $reels = Reel::where('is_active', true)
            ->with(['products' => function ($q) {
                $q->select('id', 'name', 'slug', 'price', 'old_price', 'rating', 'review_count', 'badge');
            }, 'products.images' => function ($q) {
                $q->select('id', 'product_id', 'url', 'alt')->orderBy('display_order');
            }])
            ->orderBy('display_order')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn ($reel) => [
                'id' => $reel->id,
                'title' => $reel->title,
                'description' => $reel->description,
                'videoUrl' => $reel->video_url,
                'imageUrl' => $reel->image_url,
                'linkUrl' => $reel->link_url,
                'displayOrder' => $reel->display_order,
                'products' => $reel->products->map(fn ($p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'slug' => $p->slug,
                    'price' => $p->price,
                    'old_price' => $p->old_price,
                    'rating' => $p->rating,
                    'review_count' => $p->review_count,
                    'badge' => $p->badge,
                    'image_url' => $p->images->first()?->url ?? null,
                ]),
            ]);

        return response()->json([
            'success' => true,
            'data' => $reels,
        ]);
    }

    /**
     * Admin: List all reels (including inactive) with pagination.
     * GET /api/v1/admin/reels
     */
    public function adminIndex(Request $request): JsonResponse
    {
        $query = Reel::orderBy('display_order')->orderBy('created_at', 'desc');

        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        if ($search = $request->search) {
            $query->where('title', 'like', "%{$search}%");
        }

        $reels = $query->paginate($request->per_page ?? 50);

        return response()->json([
            'success' => true,
            'data' => $reels->items(),
            'pagination' => [
                'page' => $reels->currentPage(),
                'pages' => $reels->lastPage(),
                'total' => $reels->total(),
                'per_page' => $reels->perPage(),
            ],
        ]);
    }

    /**
     * Admin: Show a single reel.
     * GET /api/v1/admin/reels/{id}
     */
    public function show(string $id): JsonResponse
    {
        $reel = Reel::with('products')->findOrFail($id);
        return response()->json(['success' => true, 'data' => $reel]);
    }

    /**
     * Admin: Create a reel.
     * POST /api/v1/admin/reels
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'video_url' => 'nullable|string|max:2048',
            'image_url' => 'nullable|string|max:2048',
            'link_url' => 'nullable|string|max:2048',
            'display_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
            'product_ids' => 'nullable|array',
            'product_ids.*' => 'string|exists:products,id',
        ]);

        $reel = Reel::create($validated);

        if ($request->has('product_ids')) {
            $syncData = [];
            foreach ($request->product_ids as $i => $pid) {
                $syncData[$pid] = ['display_order' => $i];
            }
            $reel->products()->sync($syncData);
        }

        $reel->load('products');

        return response()->json([
            'success' => true,
            'message' => 'Reel created',
            'data' => $reel,
        ], 201);
    }

    /**
     * Admin: Update a reel.
     * PUT /api/v1/admin/reels/{id}
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $reel = Reel::findOrFail($id);

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:1000',
            'video_url' => 'nullable|string|max:2048',
            'image_url' => 'nullable|string|max:2048',
            'link_url' => 'nullable|string|max:2048',
            'display_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
            'product_ids' => 'nullable|array',
            'product_ids.*' => 'string|exists:products,id',
        ]);

        $reel->update($validated);

        if ($request->has('product_ids')) {
            $syncData = [];
            foreach ($request->product_ids as $i => $pid) {
                $syncData[$pid] = ['display_order' => $i];
            }
            $reel->products()->sync($syncData);
        }

        $reel->load('products');

        return response()->json([
            'success' => true,
            'message' => 'Reel updated',
            'data' => $reel->fresh(),
        ]);
    }

    /**
     * Admin: Delete a reel.
     * DELETE /api/v1/admin/reels/{id}
     */
    public function destroy(string $id): JsonResponse
    {
        $reel = Reel::findOrFail($id);
        $reel->delete();

        return response()->json([
            'success' => true,
            'message' => 'Reel deleted',
        ]);
    }

    /**
     * Admin: Toggle reel active status.
     * PATCH /api/v1/admin/reels/{id}/toggle
     */
    public function toggleStatus(string $id): JsonResponse
    {
        $reel = Reel::findOrFail($id);
        $reel->update(['is_active' => !$reel->is_active]);

        return response()->json([
            'success' => true,
            'message' => 'Reel status toggled',
            'data' => $reel->fresh(),
        ]);
    }

    /**
     * Admin: Reorder reels.
     * PATCH /api/v1/admin/reels/reorder
     */
    public function reorder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'reels' => 'required|array|min:1',
            'reels.*.id' => 'required|string|exists:reels,id',
            'reels.*.display_order' => 'required|integer|min:0',
        ]);

        foreach ($validated['reels'] as $item) {
            Reel::where('id', $item['id'])->update(['display_order' => $item['display_order']]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Reels reordered',
        ]);
    }
}
