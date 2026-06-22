<?php

namespace App\Repositories;

use App\Models\WishlistItem;
use Illuminate\Database\Eloquent\Collection;

class WishlistRepository extends BaseRepository
{
    protected function modelClass(): string
    {
        return WishlistItem::class;
    }

    /**
     * Get user's wishlist items with pagination and product details.
     */
    public function getUserWishlist(string $userId, int $page = 1, int $limit = 20): array
    {
        $query = WishlistItem::with('product.images')
            ->where('user_id', $userId);

        $total = $query->count();

        $items = $query->latest()
            ->skip(($page - 1) * $limit)
            ->take($limit)
            ->get();

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => (int) ceil($total / $limit),
        ];
    }

    /**
     * Find wishlist item by user and product.
     */
    public function findByUserAndProduct(string $userId, string $productId): ?WishlistItem
    {
        return WishlistItem::where('user_id', $userId)
            ->where('product_id', $productId)
            ->first();
    }

    /**
     * Add item to wishlist.
     */
    public function addItem(string $userId, string $productId): array
    {
        $item = WishlistItem::create(['user_id' => $userId, 'product_id' => $productId]);
        $item->load('product.images');
        return $item->toArray();
    }

    /**
     * Toggle wishlist item (add if not exists, remove if exists).
     */
    public function toggleItem(string $userId, string $productId): array
    {
        $existing = $this->findByUserAndProduct($userId, $productId);
        if ($existing) {
            $existing->delete();
            return ['wishlisted' => false, 'message' => 'Removed from wishlist'];
        }

        WishlistItem::create(['user_id' => $userId, 'product_id' => $productId]);
        return ['wishlisted' => true, 'message' => 'Added to wishlist'];
    }

    /**
     * Check if product is in user's wishlist.
     */
    public function checkProduct(string $userId, string $productId): bool
    {
        return WishlistItem::where('user_id', $userId)
            ->where('product_id', $productId)
            ->exists();
    }

    /**
     * Get wishlist item count for user.
     */
    public function getCount(string $userId): int
    {
        return WishlistItem::where('user_id', $userId)->count();
    }

    /**
     * Remove item by user and product.
     */
    public function removeByUserAndProduct(string $userId, string $productId): void
    {
        WishlistItem::where('user_id', $userId)
            ->where('product_id', $productId)
            ->delete();
    }

    /**
     * Clear user's entire wishlist.
     */
    public function clearWishlist(string $userId): void
    {
        WishlistItem::where('user_id', $userId)->delete();
    }

    /**
     * Get multiple wishlist items by product IDs.
     */
    public function getWishlistItemsByProductIds(string $userId, array $productIds): Collection
    {
        return WishlistItem::where('user_id', $userId)
            ->whereIn('product_id', $productIds)
            ->get();
    }
}
