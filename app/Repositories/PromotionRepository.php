<?php

namespace App\Repositories;

use App\Models\Promotion;

class PromotionRepository extends BaseRepository
{
    protected function modelClass(): string
    {
        return Promotion::class;
    }

    public function getActive(): \Illuminate\Database\Eloquent\Collection
    {
        return Promotion::with(['products:id,name,slug,price', 'categories:id,name,slug'])
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', now());
            })
            ->orderBy('priority', 'desc')
            ->get();
    }

    /**
     * Get all promotions with filters and pagination.
     */
    public function findAll(array $filters = [], int $page = 1, int $limit = 20): array
    {
        $query = Promotion::with(['products:id,name,slug,price', 'categories:id,name,slug']);

        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('coupon_code', 'like', "%{$search}%");
            });
        }

        $total = $query->count();

        $promotions = $query->orderBy('priority', 'desc')
            ->skip(($page - 1) * $limit)
            ->take($limit)
            ->get();

        // Map snake_case DB fields to camelCase expected by frontend
        $items = $promotions->map(function ($promotion) {
            $products = $promotion->products->map(fn($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'slug' => $p->slug,
                'price' => $p->price,
            ]);
            $categories = $promotion->categories->map(fn($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'slug' => $c->slug,
            ]);
            return array_merge($promotion->toArray(), [
                'startDate' => $promotion->start_date,
                'endDate'   => $promotion->end_date,
                'isActive'  => $promotion->is_active ?? false,
                'imageUrl'  => $promotion->image_url,
                'productIds' => $products->pluck('id')->toArray(),
                'categoryIds' => $categories->pluck('id')->toArray(),
                'products' => $products->toArray(),
                'categories' => $categories->toArray(),
            ]);
        })->toArray();

        return [
            'items' => $items,
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'total_pages' => (int) ceil($total / $limit),
        ];
    }

    /**
     * Find promotion by coupon code.
     */
    public function findByCouponCode(string $code): ?Promotion
    {
        return Promotion::with(['products:id,name,slug,price', 'categories:id,name,slug'])
            ->where('coupon_code', $code)->first();
    }

    /**
     * Find promotions by type.
     */
    public function findByType(string $type): \Illuminate\Database\Eloquent\Collection
    {
        return Promotion::with(['products:id,name,slug,price', 'categories:id,name,slug'])
            ->where('type', $type)
            ->where('is_active', true)
            ->orderBy('priority', 'desc')
            ->get();
    }

    /**
     * Update promotion status.
     */
    public function updateStatus(string $id, string $status): Promotion
    {
        $promotion = $this->findByIdOrFail($id);
        $promotion->update(['status' => $status]);
        return $promotion->fresh();
    }

    /**
     * Create a promotion with optional product/category pivot sync.
     */
    public function createWithRelations(array $data, array $productIds = [], array $categoryIds = []): Promotion
    {
        $promotion = Promotion::create($data);

        if (!empty($productIds)) {
            $promotion->products()->sync($productIds);
        }
        if (!empty($categoryIds)) {
            $promotion->categories()->sync($categoryIds);
        }

        return $promotion->fresh()->load(['products:id,name,slug,price', 'categories:id,name,slug']);
    }

    /**
     * Update a promotion with optional product/category pivot sync.
     * Pass null for productIds/categoryIds to leave associations unchanged.
     * Pass an empty array to clear all associations.
     */
    public function updateWithRelations(string $id, array $data, ?array $productIds = null, ?array $categoryIds = null): Promotion
    {
        $promotion = $this->findByIdOrFail($id);
        $promotion->update($data);

        if ($productIds !== null) {
            $promotion->products()->sync($productIds);
        }
        if ($categoryIds !== null) {
            $promotion->categories()->sync($categoryIds);
        }

        return $promotion->fresh()->load(['products:id,name,slug,price', 'categories:id,name,slug']);
    }

    /**
     * Find a promotion by ID with relations loaded.
     */
    public function findByIdWithRelations(string $id): ?Promotion
    {
        return Promotion::with(['products:id,name,slug,price', 'categories:id,name,slug'])->find($id);
    }
}
