<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ProductService;
use App\Exceptions\AppError;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function __construct(
        protected ProductService $productService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $products = $this->productService->getAll($request->all());
        return response()->json(['success' => true, 'data' => $products]);
    }

    public function featured(Request $request): JsonResponse
    {
        $products = $this->productService->getFeatured($request->limit ?? 8);
        return response()->json(['success' => true, 'data' => $products]);
    }

    public function newArrivals(Request $request): JsonResponse
    {
        $products = $this->productService->getNewArrivals($request->limit ?? 8);
        return response()->json(['success' => true, 'data' => $products]);
    }

    public function bestSellers(Request $request): JsonResponse
    {
        $products = $this->productService->getBestSellers($request->limit ?? 8);
        return response()->json(['success' => true, 'data' => $products]);
    }

    public function search(Request $request): JsonResponse
    {
        try {
            $results = $this->productService->search($request->q, $request->limit ?? 10);
            return response()->json(['success' => true, 'data' => $results]);
        } catch (AppError $e) {
            return $e->render();
        }
    }

    public function byCategory(Request $request, string $categoryId): JsonResponse
    {
        $products = $this->productService->getByCategory($categoryId, $request->all());
        return response()->json(['success' => true, 'data' => $products]);
    }

    public function show(string $id): JsonResponse
    {
        try {
            $product = $this->productService->getById($id);
            return response()->json(['success' => true, 'data' => $product]);
        } catch (AppError $e) {
            return $e->render();
        }
    }

    public function checkAvailability(Request $request, string $id): JsonResponse
    {
        $available = $this->productService->checkAvailability($id, $request->quantity ?? 1);
        return response()->json(['success' => true, 'data' => ['available' => $available]]);
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'price' => 'required|numeric|min:0',
                'sku' => 'nullable|string|unique:products',
                'category_id' => 'nullable|exists:categories,id',
                'quantity' => 'nullable|integer|min:0',
                'status' => 'nullable|string|in:DRAFT,PUBLISHED,ARCHIVED',
            ]);

            $product = $this->productService->create($validated);
            return response()->json(['success' => true, 'message' => 'Product created', 'data' => $product], 201);
        } catch (AppError $e) {
            return $e->render();
        }
    }

    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name' => 'sometimes|string|max:255',
                'description' => 'nullable|string',
                'price' => 'sometimes|numeric|min:0',
                'sku' => 'nullable|string|unique:products,sku,' . $id,
                'status' => 'nullable|string|in:DRAFT,PUBLISHED,ARCHIVED',
            ]);

            $product = $this->productService->update($id, $validated);
            return response()->json(['success' => true, 'message' => 'Product updated', 'data' => $product]);
        } catch (AppError $e) {
            return $e->render();
        }
    }

    public function destroy(string $id): JsonResponse
    {
        try {
            $this->productService->delete($id);
            return response()->json(['success' => true, 'message' => 'Product deleted']);
        } catch (AppError $e) {
            return $e->render();
        }
    }

    public function publishProduct(string $id): JsonResponse
    {
        try {
            $product = $this->productService->publish($id);
            return response()->json(['success' => true, 'message' => 'Product published', 'data' => $product]);
        } catch (AppError $e) {
            return $e->render();
        }
    }

    public function archiveProduct(string $id): JsonResponse
    {
        try {
            $product = $this->productService->archive($id);
            return response()->json(['success' => true, 'message' => 'Product archived', 'data' => $product]);
        } catch (AppError $e) {
            return $e->render();
        }
    }

    public function getLowStockProducts(Request $request): JsonResponse
    {
        $threshold = $request->threshold ?? 5;
        $products = $this->productService->getLowStock((int) $threshold);
        return response()->json(['success' => true, 'data' => $products]);
    }

    public function importProductsFromCSV(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'file' => 'required|file|mimes:csv,txt|max:5120',
            ]);

            $file = $request->file('file');
            $csvContent = file_get_contents($file->getPathname());

            // Use the dedicated ProductImportService for consistent CSV parsing
            $importService = app(\App\Services\ProductImportService::class);
            $result = $importService->importFromCSV($csvContent);

            // Clear dashboard cache since products/inventory changed
            app(\App\Repositories\AdminRepository::class)->clearDashboardCache();

            return response()->json(['success' => true, 'message' => "Imported {$result['imported']} products, skipped {$result['skipped']}", 'data' => $result]);
        } catch (AppError $e) {
            return $e->render();
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
