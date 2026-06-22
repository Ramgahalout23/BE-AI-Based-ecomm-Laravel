<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AIGeneralService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AIController extends Controller
{
    protected AIGeneralService $aiGeneralService;

    public function __construct()
    {
        $this->aiGeneralService = new AIGeneralService();
    }

    /**
     * Generate product description using AI.
     */
    public function generateProductDescription(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_name' => 'required|string',
            'features' => 'nullable|string',
            'tone' => 'nullable|string',
            'category' => 'nullable|string',
            'price' => 'nullable|numeric',
            'keywords' => 'nullable|string',
            'target_audience' => 'nullable|string',
            'brand_name' => 'nullable|string',
        ]);

        try {
            $result = $this->aiGeneralService->generateProductDescription([
                'name' => $validated['product_name'],
                'features' => !empty($validated['features']) ? explode(',', $validated['features']) : [],
                'tone' => $validated['tone'] ?? 'professional',
                'category' => $validated['category'] ?? null,
                'price' => $validated['price'] ?? null,
                'keywords' => $validated['keywords'] ?? null,
                'target_audience' => $validated['target_audience'] ?? null,
                'brand_name' => $validated['brand_name'] ?? null,
            ]);

            return response()->json(['success' => true, 'data' => $result]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Generate short description using AI.
     */
    public function generateShortDescription(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_name' => 'required|string',
            'tone' => 'nullable|string',
            'category' => 'nullable|string',
        ]);

        try {
            $description = $this->aiGeneralService->generateShortDescription([
                'name' => $validated['product_name'],
                'tone' => $validated['tone'] ?? 'professional',
                'category' => $validated['category'] ?? null,
            ]);

            return response()->json(['success' => true, 'data' => ['description' => $description]]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Generate SEO meta using AI.
     */
    public function generateSeoMeta(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'type' => 'required|in:product,category,page',
            'description' => 'nullable|string',
            'category' => 'nullable|string',
            'brand_name' => 'nullable|string',
        ]);

        try {
            $result = $this->aiGeneralService->generateSeoMeta($validated);
            return response()->json(['success' => true, 'data' => $result]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Generate an image using AI.
     */
    public function generateImage(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'prompt' => 'required|string',
            'product_name' => 'nullable|string',
            'style' => 'nullable|in:product-photo,lifestyle,flat-lay,studio,abstract,banner',
            'size' => 'nullable|in:1024x1024,1792x1024,1024x1792',
            'reference_image_url' => 'nullable|url',
        ]);

        try {
            $result = $this->aiGeneralService->generateImage($validated);
            return response()->json(['success' => true, 'data' => $result]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Generate category description using AI.
     */
    public function generateCategoryDescription(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category_name' => 'required|string',
            'parent_name' => 'nullable|string',
            'product_count' => 'nullable|integer',
        ]);

        try {
            $result = $this->aiGeneralService->generateCategoryDescription([
                'name' => $validated['category_name'],
                'parentName' => $validated['parent_name'] ?? null,
                'productCount' => $validated['product_count'] ?? null,
            ]);

            return response()->json(['success' => true, 'data' => $result]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Generate variant description using AI.
     */
    public function generateVariantDescription(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'variant_name' => 'required|string',
            'product_name' => 'nullable|string',
            'color' => 'nullable|string',
            'size' => 'nullable|string',
            'sku' => 'nullable|string',
            'category' => 'nullable|string',
            'tone' => 'nullable|string',
        ]);

        try {
            $result = $this->aiGeneralService->generateVariantDescription([
                'productName' => $validated['product_name'] ?? $validated['variant_name'],
                'color' => $validated['color'] ?? null,
                'size' => $validated['size'] ?? null,
                'sku' => $validated['sku'] ?? null,
                'category' => $validated['category'] ?? null,
                'tone' => $validated['tone'] ?? null,
            ]);

            return response()->json(['success' => true, 'data' => $result]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Generate variant images using AI.
     */
    public function generateVariantImages(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_name' => 'required|string',
            'variant_name' => 'nullable|string',
            'color' => 'nullable|string',
            'size' => 'nullable|string',
            'style' => 'nullable|string',
            'reference_image_url' => 'nullable|url',
        ]);

        try {
            $result = $this->aiGeneralService->generateVariantImages([
                'productName' => $validated['product_name'],
                'color' => $validated['color'] ?? null,
                'size' => $validated['size'] ?? null,
                'style' => $validated['style'] ?? null,
                'referenceImageUrl' => $validated['reference_image_url'] ?? null,
            ]);

            return response()->json(['success' => true, 'data' => $result]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Generate variant images with stream progress.
     */
    public function generateVariantImagesStream(Request $request): JsonResponse
    {
        // For server-sent events, we'd use a streaming response.
        // For now, return the standard variant images result.
        return $this->generateVariantImages($request);
    }

    /**
     * Test AI provider connection.
     */
    public function testConnection(Request $request): JsonResponse
    {
        $apiKey = $request->input('api_key');
        $baseUrl = $request->input('base_url');
        $chatModel = $request->input('chat_model');

        $config = [];
        if ($apiKey) $config['api_key'] = $apiKey;
        if ($baseUrl) $config['base_url'] = $baseUrl;
        if ($chatModel) $config['chat_model'] = $chatModel;

        $result = $this->aiGeneralService->testConnection($config);
        return response()->json(['success' => true, 'data' => $result]);
    }

    /**
     * Generate CMS page content using AI.
     */
    public function generatePageContent(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page_type' => 'required|string',
            'topic' => 'nullable|string',
            'tone' => 'nullable|string',
        ]);

        try {
            $result = $this->aiGeneralService->generatePageContent([
                'title' => $validated['page_type'],
                'description' => $validated['topic'] ?? null,
                'tone' => $validated['tone'] ?? 'professional',
            ]);

            return response()->json(['success' => true, 'data' => $result]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
