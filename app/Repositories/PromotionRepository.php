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
        return Promotion::where('is_active', true)
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
        $query = Promotion::query();

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
        // Use array_merge instead of dynamic properties because Eloquent toArray() doesn't serialize dynamic props
        $items = $promotions->map(function ($promotion) {
            return array_merge($promotion->toArray(), [
                'startDate' => $promotion->start_date,
                'endDate'   => $promotion->end_date,
                'isActive'  => $promotion->is_active ?? false,
                'imageUrl'  => $promotion->image_url,
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
        return Promotion::where('coupon_code', $code)->first();
    }

    /**
     * Find promotions by type.
     */
    public function findByType(string $type): \Illuminate\Database\Eloquent\Collection
    {
        return Promotion::where('type', $type)
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
}
