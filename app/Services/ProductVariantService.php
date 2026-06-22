<?php

namespace App\Services;

use App\Repositories\ProductVariantRepository;
use App\Exceptions\AppError;

class ProductVariantService
{
    public function __construct(
        protected ProductVariantRepository $variantRepository
    ) {}

    public function getByProduct(string $productId): array
    {
        return $this->variantRepository->findByProduct($productId)->toArray();
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
        return $variant->toArray();
    }

    public function update(string $id, array $data): array
    {
        $this->variantRepository->findByIdOrFail($id);
        return $this->variantRepository->update($id, $data)->toArray();
    }

    public function delete(string $id): void
    {
        $this->variantRepository->findByIdOrFail($id);
        $this->variantRepository->delete($id);
    }

    public function getLowStock(int $threshold = 5): array
    {
        return $this->variantRepository->getLowStock($threshold)->toArray();
    }
}
