<?php

namespace App\Services;

use App\Repositories\BrandRepository;
use App\Exceptions\AppError;
use Illuminate\Support\Str;

class BrandService
{
    public function __construct(
        protected BrandRepository $brandRepository
    ) {}

    public function getAll(): array
    {
        return $this->brandRepository->all()->toArray();
    }

    public function getById(string $id): array
    {
        $brand = $this->brandRepository->findById($id);
        if (!$brand) throw AppError::notFound('Brand not found');
        return $brand->toArray();
    }

    public function create(array $data): array
    {
        $data['slug'] = $data['slug'] ?? Str::slug($data['name']);
        return $this->brandRepository->create($data)->toArray();
    }

    public function update(string $id, array $data): array
    {
        $this->brandRepository->findByIdOrFail($id);
        return $this->brandRepository->update($id, $data)->toArray();
    }

    public function delete(string $id): void
    {
        $this->brandRepository->findByIdOrFail($id);
        $this->brandRepository->delete($id);
    }
}
