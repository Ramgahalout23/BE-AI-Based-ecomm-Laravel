<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Traits\MapsCamelCaseFields;
use App\Services\PromotionService;
use App\Exceptions\AppError;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PromotionController extends Controller
{
    use MapsCamelCaseFields;

    private array $fieldMappings = [
        'imageUrl' => 'image_url',
        'startDate' => 'start_date',
        'endDate' => 'end_date',
        'isActive' => 'is_active',
        'productIds' => 'product_ids',
        'categoryIds' => 'category_ids',
    ];
    public function __construct(protected PromotionService $promotionService) {}

    public function index(): JsonResponse
    {
        $active = $this->promotionService->getActive();
        // Include a flat list of product IDs and category IDs for storefront lookup
        return response()->json(['success' => true, 'data' => $active]);
    }

    public function adminIndex(Request $request): JsonResponse
    {
        $page = (int)($request->page ?? 1);
        $limit = (int)($request->limit ?? 20);
        $filters = $request->only(['status', 'search', 'type']);
        $result = $this->promotionService->getAllPromotions($filters, $page, $limit);
        return response()->json([
            'success' => true,
            'data' => [
                'items' => $result['items'] ?? [],
                'pagination' => [
                    'page' => $result['page'] ?? $page,
                    'pages' => $result['total_pages'] ?? 1,
                    'total' => $result['total'] ?? 0,
                    'per_page' => $result['limit'] ?? $limit,
                ],
            ],
        ]);
    }

    public function show(string $id): JsonResponse
    {
        try {
            return response()->json(['success' => true, 'data' => $this->promotionService->getById($id)]);
        } catch (AppError $e) { return $e->render(); }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $input = $this->mapCamelCase($request->all(), $this->fieldMappings);

            $validated = validator($input, [
                'title' => 'required|string',
                'description' => 'nullable|string',
                'image_url' => 'nullable|string',
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date|after_or_equal:start_date',
                'discount' => 'nullable|numeric|min:0',
                'type' => 'nullable|string',
                'status' => 'nullable|string|in:ACTIVE,PAUSED,EXPIRED',
                'is_active' => 'nullable|boolean',
                'product_ids' => 'nullable|array',
                'product_ids.*' => 'string',
                'category_ids' => 'nullable|array',
                'category_ids.*' => 'string',
            ])->validate();

            $productIds = $validated['product_ids'] ?? [];
            $categoryIds = $validated['category_ids'] ?? [];
            unset($validated['product_ids'], $validated['category_ids']);

            return response()->json([
                'success' => true,
                'message' => 'Promotion created',
                'data' => $this->promotionService->createWithRelations($validated, $productIds, $categoryIds),
            ], 201);
        } catch (AppError $e) { return $e->render(); } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        }
    }

    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $input = $this->mapCamelCase($request->all(), $this->fieldMappings);

            $validated = validator($input, [
                'title' => 'nullable|string',
                'description' => 'nullable|string',
                'image_url' => 'nullable|string',
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date|after_or_equal:start_date',
                'discount' => 'nullable|numeric|min:0',
                'status' => 'nullable|string|in:ACTIVE,PAUSED,EXPIRED',
                'is_active' => 'nullable|boolean',
                'product_ids' => 'nullable|array',
                'product_ids.*' => 'string',
                'category_ids' => 'nullable|array',
                'category_ids.*' => 'string',
            ])->validate();

            // null = not provided (don't sync), [] = explicitly empty (clear all)
            $productIds = array_key_exists('product_ids', $validated) ? ($validated['product_ids'] ?? []) : null;
            $categoryIds = array_key_exists('category_ids', $validated) ? ($validated['category_ids'] ?? []) : null;
            unset($validated['product_ids'], $validated['category_ids']);

            return response()->json([
                'success' => true,
                'message' => 'Promotion updated',
                'data' => $this->promotionService->updateWithRelations($id, $validated, $productIds, $categoryIds),
            ]);
        } catch (AppError $e) { return $e->render(); }
    }

    public function destroy(string $id): JsonResponse
    {
        try {
            $this->promotionService->delete($id);
            return response()->json(['success' => true, 'message' => 'Promotion deleted']);
        } catch (AppError $e) { return $e->render(); }
    }
}
