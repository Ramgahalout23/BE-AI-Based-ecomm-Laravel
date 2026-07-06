<?php

namespace App\Services;

use App\Repositories\ProductVariantRepository;
use App\Exceptions\AppError;
use Illuminate\Support\Facades\Cache;

class ProductVariantService
{
    public function __construct(
        protected ProductVariantRepository $variantRepository
    ) {}

    public function getByProduct(string $productId): array
    {
        return Cache::remember("product_variants_{$productId}", 3600, function () use ($productId) {
            return $this->variantRepository->findByProduct($productId)->toArray();
        });
    }

    public function getById(string $id): array
    {
        $variant = $this->variantRepository->findById($id);
        if (!$variant) throw AppError::notFound('Variant not found');
        return $variant->toArray();
    }

    public function create(string $productId, array $data): array
    {
        $data['product_id'] = $productId;
        $variant = $this->variantRepository->create($data);
        Cache::forget("product_variants_{$productId}");
        return $variant->toArray();
    }

    public function update(string $id, array $data): array
    {
        $variant = $this->variantRepository->findByIdOrFail($id);
        $result = $this->variantRepository->update($id, $data)->toArray();
        Cache::forget("product_variants_{$variant->product_id}");
        return $result;
    }

    public function delete(string $id): void
    {
        $variant = $this->variantRepository->findByIdOrFail($id);
        $this->variantRepository->delete($id);
        Cache::forget("product_variants_{$variant->product_id}");
    }

    public function getLowStock(int $threshold = 5): array
    {
        return $this->variantRepository->getLowStock($threshold)->toArray();
    }
}
