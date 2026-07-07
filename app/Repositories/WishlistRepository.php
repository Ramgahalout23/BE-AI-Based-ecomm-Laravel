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
     * Map a product array from snake_case (DB) to camelCase for the frontend.
     */
    private function productToCamelCase(?array $product): ?array
    {
        if (!$product) return null;
        $map = [
            'old_price'     => 'oldPrice',
            'is_featured'   => 'isFeatured',
            'category_id'   => 'categoryId',
            'created_at'    => 'createdAt',
            'updated_at'    => 'updatedAt',
            'review_count'  => 'reviewCount',
            'display_order' => 'displayOrder',
        ];
        foreach ($map as $snake => $camel) {
            if (array_key_exists($snake, $product)) {
                $product[$camel] = $product[$snake];
            }
        }
        return $product;
    }

    /**
     * Get user's wishlist items with pagination and product details.
     */
    public function getUserWishlist(string $userId, int $page = 1, int $limit = 20): array
    {
        $query = WishlistItem::with(['product' => function ($q) {
            $q->select('id', 'name', 'slug', 'price', 'old_price', 'status', 'quantity', 'is_featured', 'category_id');
        }, 'product.images' => function ($q) {
            $q->select('id', 'product_id', 'url', 'alt');
        }])->select('id', 'user_id', 'product_id', 'created_at')
            ->where('user_id', $userId);

        $total = $query->count();

        $items = $query->latest()
            ->skip(($page - 1) * $limit)
            ->take($limit)
            ->get()
            ->map(function ($item) {
                $data = $item->toArray();
                if (isset($data['product'])) {
                    $data['product'] = $this->productToCamelCase($data['product']);
                }
                return $data;
            });

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
