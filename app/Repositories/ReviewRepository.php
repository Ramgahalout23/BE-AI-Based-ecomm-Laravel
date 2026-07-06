<?php

namespace App\Repositories;

use App\Models\Review;
use App\Models\Product;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ReviewRepository extends BaseRepository
{
    protected function modelClass(): string
    {
        return Review::class;
    }

    /**
     * Get the review cache version for a product (incremented on mutations).
     */
    private function getReviewVersion(string $productId): int
    {
        return Cache::get("reviews_version_{$productId}", 0);
    }

    /**
     * Invalidate review caches for a product by bumping the version key.
     */
    public function clearProductReviewCache(string $productId): void
    {
        $version = $this->getReviewVersion($productId);
        Cache::forever("reviews_version_{$productId}", $version + 1);
    }

    /**
     * Get product reviews with pagination, optionally filtered by moderated status.
     */
    public function getProductReviews(string $productId, int $page = 1, int $perPage = 10, bool $onlyModerated = true): array
    {
        $version = $this->getReviewVersion($productId);
        $cacheKey = "reviews_product:{$productId}:v{$version}:page{$page}:limit{$perPage}";

        return Cache::remember($cacheKey, 60, function () use ($productId, $page, $perPage, $onlyModerated) {
            $query = Review::with(['user' => fn($q) => $q->select('id', 'first_name', 'last_name', 'email', 'avatar')])
                ->where('product_id', $productId)
                ->where('is_flagged', false);

            if ($onlyModerated) {
                $query->where('is_moderated', true);
            }

            $total = $query->count();

            $items = $query->latest()
                ->skip(($page - 1) * $perPage)
                ->take($perPage)
                ->get();

            $avgRating = Review::where('product_id', $productId)
                ->where('is_flagged', false)
                ->where('is_moderated', true)
                ->avg('rating') ?? 0;

            $totalPages = (int) ceil($total / $perPage);

            return [
                'items' => $items,
                'total' => $total,
                'page' => $page,
                'limit' => $perPage,
                'total_pages' => $totalPages,
                'average_rating' => round((float) $avgRating, 2),
            ];
        });
    }

    /**
     * Get user reviews with pagination.
     */
    public function getUserReviews(string $userId, int $page = 1, int $perPage = 10): array
    {
        $query = Review::where('user_id', $userId);

        $total = $query->count();

        $items = $query->with('product:id,name')
            ->latest()
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get();

        $totalPages = (int) ceil($total / $perPage);

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'limit' => $perPage,
            'total_pages' => $totalPages,
        ];
    }

    /**
     * Get review stats for a product — cached with version invalidation.
     */
    public function getStats(string $productId): array
    {
        $version = $this->getReviewVersion($productId);
        $cacheKey = "reviews_stats:{$productId}:v{$version}";

        return Cache::remember($cacheKey, 120, function () use ($productId) {
            $distribution = Review::where('product_id', $productId)
                ->where('is_flagged', false)
                ->where('is_moderated', true)
                ->selectRaw('rating, count(*) as count')
                ->groupBy('rating')
                ->pluck('count', 'rating')
                ->toArray();

            $filled = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
            foreach ($distribution as $rating => $count) {
                $filled[(int) $rating] = $count;
            }

            $total = array_sum($filled);
            $average = $total > 0
                ? round(array_sum(array_map(fn($r, $c) => $r * $c, [1,2,3,4,5], $filled)) / $total, 2)
                : 0;

            return [
                'average' => $average,
                'total' => $total,
                'distribution' => $filled,
            ];
        });
    }

    /**
     * Update product rating and review count.
     */
    public function updateProductRating(string $productId): void
    {
        $stats = Review::where('product_id', $productId)
            ->where('is_moderated', true)
            ->selectRaw('avg(rating) as avg_rating, count(*) as review_count')
            ->first();

        Product::where('id', $productId)->update([
            'rating' => $stats->avg_rating ?? 0,
            'review_count' => $stats->review_count ?? 0,
        ]);
    }

    /**
     * Get single review by ID with user and product.
     */
    public function getReviewById(string $reviewId): ?Review
    {
        return Review::with(['user' => fn($q) => $q->select('id', 'first_name', 'last_name', 'email', 'avatar')])
            ->with('product:id,name')
            ->find($reviewId);
    }

    /**
     * Check if user has already reviewed a product.
     */
    public function hasUserReviewedProduct(string $userId, string $productId): ?Review
    {
        return Review::where('user_id', $userId)
            ->where('product_id', $productId)
            ->first();
    }

    /**
     * Mark review as helpful (increment count).
     */
    public function markHelpful(string $reviewId): Review
    {
        $review = $this->findByIdOrFail($reviewId);
        $review->increment('helpful');
        return $review->fresh();
    }

    /**
     * Mark review as unhelpful (increment count).
     */
    public function markUnhelpful(string $reviewId): Review
    {
        $review = $this->findByIdOrFail($reviewId);
        $review->increment('unhelpful');
        return $review->fresh();
    }

    /**
     * Get pending moderation reviews with search.
     */
    public function getPendingModeration(int $page = 1, int $perPage = 20, ?string $search = null): array
    {
        $query = Review::with(['user' => fn($q) => $q->select('id', 'first_name', 'last_name', 'email', 'avatar')])
            ->with('product:id,name')
            ->where('is_moderated', false)
            ->where('is_flagged', false);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('comment', 'like', "%{$search}%");
            });
        }

        $total = $query->count();
        $items = $query->orderBy('created_at')
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get();

        $totalPages = (int) ceil($total / $perPage);

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'limit' => $perPage,
            'total_pages' => $totalPages,
        ];
    }

    /**
     * Flag a review as inappropriate.
     */
    public function flagReview(string $reviewId): Review
    {
        $review = $this->findByIdOrFail($reviewId);
        $review->update(['is_flagged' => true]);
        return $review->fresh();
    }

    /**
     * Approve a review (moderated).
     */
    public function approveReview(string $reviewId): Review
    {
        $review = $this->findByIdOrFail($reviewId);
        $review->update(['is_moderated' => true]);
        return $review->fresh();
    }

    /**
     * Reject a review (flag it as inappropriate).
     */
    public function rejectReview(string $reviewId): Review
    {
        $review = $this->findByIdOrFail($reviewId);
        $review->update(['is_flagged' => true, 'is_moderated' => false]);
        return $review->fresh();
    }

    /**
     * Get verified purchase reviews only — cached with version invalidation.
     */
    public function getVerifiedReviews(string $productId, int $page = 1, int $perPage = 10): array
    {
        $version = $this->getReviewVersion($productId);
        $cacheKey = "reviews_verified:{$productId}:v{$version}:page{$page}:limit{$perPage}";

        return Cache::remember($cacheKey, 60, function () use ($productId, $page, $perPage) {
            $query = Review::with(['user' => fn($q) => $q->select('id', 'first_name', 'last_name', 'email', 'avatar')])
                ->where('product_id', $productId)
                ->where('is_verified', true)
                ->where('is_flagged', false)
                ->where('is_moderated', true);

            $total = $query->count();
            $items = $query->latest()
                ->skip(($page - 1) * $perPage)
                ->take($perPage)
                ->get();

            $totalPages = (int) ceil($total / $perPage);

            return [
                'items' => $items,
                'total' => $total,
                'page' => $page,
                'limit' => $perPage,
                'total_pages' => $totalPages,
            ];
        });
    }
}
