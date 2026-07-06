<?php

namespace App\Services;

use App\Models\Brand;
use App\Repositories\BrandRepository;
use App\Exceptions\AppError;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class BrandService
{
    public function __construct(
        protected BrandRepository $brandRepository
    ) {}

    public function getAll(): array
    {
        return Cache::remember('brands_all', 3600, function () {
            return Brand::select('id', 'name', 'slug', 'image_url')
                ->orderBy('name')
                ->get()
                ->toArray();
        });
    }

    public function getById(string $id): array
    {
        $cacheKey = 'brand_' . $id;
        return Cache::remember($cacheKey, 3600, function () use ($id) {
            $brand = $this->brandRepository->findById($id);
            if (!$brand) throw AppError::notFound('Brand not found');
            return $brand->toArray();
        });
    }

    public function create(array $data): array
    {
        $data['slug'] = $data['slug'] ?? Str::slug($data['name']);
        $result = $this->brandRepository->create($data)->toArray();
        Cache::forget('brands_all');
        return $result;
    }

    public function update(string $id, array $data): array
    {
        $this->brandRepository->findByIdOrFail($id);
        $result = $this->brandRepository->update($id, $data)->toArray();
        Cache::forget('brands_all');
        Cache::forget('brand_' . $id);
        return $result;
    }

    public function delete(string $id): void
    {
        $this->brandRepository->findByIdOrFail($id);
        $this->brandRepository->delete($id);
        Cache::forget('brands_all');
        Cache::forget('brand_' . $id);
    }
}
