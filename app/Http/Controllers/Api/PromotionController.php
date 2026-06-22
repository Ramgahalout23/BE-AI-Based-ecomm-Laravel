<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PromotionService;
use App\Exceptions\AppError;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PromotionController extends Controller
{
    public function __construct(protected PromotionService $promotionService) {}

    public function index(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->promotionService->getActive()]);
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
            $data = $request->all();
            // Map camelCase payload from frontend to snake_case
            if (isset($data['imageUrl'])) $data['image_url'] = $data['imageUrl'];
            if (isset($data['startDate'])) $data['start_date'] = $data['startDate'];
            if (isset($data['endDate'])) $data['end_date'] = $data['endDate'];
            if (isset($data['isActive'])) $data['is_active'] = $data['isActive'];
            if (isset($data['discount'])) $data['discount'] = $data['discount'];

            $validated = validator($data, [
                'title' => 'required|string',
                'description' => 'nullable|string',
                'image_url' => 'nullable|string',
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date|after_or_equal:start_date',
                'discount' => 'nullable|numeric|min:0',
                'type' => 'nullable|string',
                'status' => 'nullable|string|in:ACTIVE,PAUSED,EXPIRED',
                'is_active' => 'nullable|boolean',
            ])->validate();

            return response()->json(['success' => true, 'message' => 'Promotion created', 'data' => $this->promotionService->create($validated)], 201);
        } catch (AppError $e) { return $e->render(); } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        }
    }

    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $data = $request->all();
            // Map camelCase payload from frontend to snake_case
            if (isset($data['imageUrl'])) $data['image_url'] = $data['imageUrl'];
            if (isset($data['startDate'])) $data['start_date'] = $data['startDate'];
            if (isset($data['endDate'])) $data['end_date'] = $data['endDate'];
            if (isset($data['isActive'])) $data['is_active'] = $data['isActive'];

            return response()->json(['success' => true, 'message' => 'Promotion updated', 'data' => $this->promotionService->update($id, $data)]);
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
