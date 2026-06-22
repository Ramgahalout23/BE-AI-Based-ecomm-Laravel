<?php

namespace App\Repositories;

use App\Models\Product;
use Illuminate\Pagination\LengthAwarePaginator;

class ProductRepository extends BaseRepository
{
    protected function modelClass(): string
    {
        return Product::class;
    }

    public function findWithDetails(string $id): ?Product
    {
        return Product::with([
            'category:id,name,slug,image',
            'brand:id,name,slug,logo',
            'images:id,product_id,url,alt,display_order',
            'variants:id,product_id,name,sku,attributes,price,quantity,images',
            'inventory:id,product_id,total_quantity,available_quantity',
            'reviews.user:id,first_name,last_name,avatar',
        ])->find($id);
    }

    public function findBySlug(string $slug): ?Product
    {
        return Product::with([
            'category:id,name,slug,image',
            'brand:id,name,slug,logo',
            'images:id,product_id,url,alt,display_order',
            'variants:id,product_id,name,sku,attributes,price,quantity,images',
            'inventory:id,product_id,total_quantity,available_quantity',
        ])->where('slug', $slug)->first();
    }

    public function findBySku(string $sku): ?Product
    {
        return Product::where('sku', $sku)->first();
    }

    public function findMany(array $filters = []): LengthAwarePaginator
    {
        $query = Product::with([
            'category:id,name,slug,image',
            'images:id,product_id,url,alt,display_order',
            'variants:id,product_id,name,sku,attributes,price,quantity,images',
        ]);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        } else {
            $query->where('status', 'PUBLISHED');
        }

        if (!empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        if (!empty($filters['brand_id'])) {
            $query->where('brand_id', $filters['brand_id']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        if (isset($filters['min_price'])) {
            $query->where('price', '>=', $filters['min_price']);
        }
        if (isset($filters['max_price'])) {
            $query->where('price', '<=', $filters['max_price']);
        }

        if (!empty($filters['is_featured'])) {
            $query->where('is_featured', true);
        }

        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';
        $query->orderBy($sortBy, $sortOrder);

        $perPage = $filters['per_page'] ?? 20;
        return $query->paginate($perPage);
    }

    public function getFeatured(int $limit = 8): \Illuminate\Database\Eloquent\Collection
    {
        return Product::with([
            'category:id,name,slug,image',
            'images:id,product_id,url,alt,display_order',
            'variants:id,product_id,name,sku,attributes,price,quantity,images',
        ])->where('status', 'PUBLISHED')
            ->where('is_featured', true)
            ->latest()
            ->take($limit)
            ->get();
    }

    public function getNewArrivals(int $limit = 8): \Illuminate\Database\Eloquent\Collection
    {
        return Product::with([
            'category:id,name,slug,image',
            'images:id,product_id,url,alt,display_order',
            'variants:id,product_id,name,sku,attributes,price,quantity,images',
        ])->where('status', 'PUBLISHED')
            ->latest()
            ->take($limit)
            ->get();
    }

    public function getBestSellers(int $limit = 8): \Illuminate\Database\Eloquent\Collection
    {
        return Product::with([
            'category:id,name,slug,image',
            'images:id,product_id,url,alt,display_order',
            'variants:id,product_id,name,sku,attributes,price,quantity,images',
        ])->where('status', 'PUBLISHED')
            ->orderBy('view_count', 'desc')
            ->take($limit)
            ->get();
    }

    public function findLowStock(int $threshold = 5): \Illuminate\Database\Eloquent\Collection
    {
        return Product::with('inventory:id,product_id,total_quantity,available_quantity')
            ->where('status', 'PUBLISHED')
            ->whereHas('inventory', function ($q) use ($threshold) {
                $q->where('available_quantity', '<=', $threshold);
            })
            ->get();
    }

    public function search(string $query, int $limit = 10): \Illuminate\Database\Eloquent\Collection
    {
        // Try FULLTEXT search first for optimal performance
        try {
            return Product::with([
                'variants:id,product_id,name,sku,attributes,price,quantity,images',
                'images:id,product_id,url,alt,display_order',
            ])->where('status', 'PUBLISHED')
                ->where(function ($q) use ($query) {
                    $escapedQuery = str_replace(['"', "'"], '', $query); // Strip quotes for BOOLEAN mode
                    if (strlen($escapedQuery) >= 3) {
                        $q->whereRaw('MATCH(name) AGAINST(? IN BOOLEAN MODE)', ['+' . $escapedQuery . '*']);
                    } else {
                        $q->where('name', 'like', "%{$query}%");
                    }
                })
                ->take($limit)
                ->get();
        } catch (\Exception $e) {
            // Fallback to LIKE search if FULLTEXT is not available
            return Product::with([
                'variants:id,product_id,name,sku,attributes,price,quantity,images',
                'images:id,product_id,url,alt,display_order',
            ])->where('status', 'PUBLISHED')
                ->where(function ($q) use ($query) {
                    $q->where('name', 'like', "%{$query}%")
                      ->orWhere('description', 'like', "%{$query}%");
                })
                ->take($limit)
                ->get();
        }
    }

    public function updateStock(string $id, int $quantity): Product
    {
        $product = $this->findByIdOrFail($id);
        $product->decrement('quantity', $quantity);
        return $product->fresh();
    }

    public function incrementStock(string $id, int $quantity): Product
    {
        $product = $this->findByIdOrFail($id);
        $product->increment('quantity', $quantity);
        return $product->fresh();
    }

    public function incrementViewCount(string $productId): void
    {
        Product::where('id', $productId)->increment('view_count');
    }

    public function updateProductRating(string $productId): void
    {
        $product = Product::find($productId);
        if (!$product) return;

        $ratingData = \App\Models\Review::where('product_id', $productId)
            ->where('is_moderated', true)
            ->selectRaw('AVG(rating) as avg_rating, COUNT(*) as review_count')
            ->first();

        if (!$ratingData || !$ratingData->avg_rating) return;

        $product->update([
            'rating' => round($ratingData->avg_rating, 2),
            'review_count' => (int) $ratingData->review_count,
        ]);
    }

    public function getRelatedProducts(string $productId, int $limit = 5): \Illuminate\Database\Eloquent\Collection
    {
        $relatedIds = \App\Models\ProductRelation::where('product_id', $productId)
            ->pluck('related_product_id')
            ->merge(
                \App\Models\ProductRelation::where('related_product_id', $productId)
                    ->pluck('product_id')
            )
            ->unique()
            ->toArray();

        if (empty($relatedIds)) {
            return collect([]);
        }

        return Product::with(['images:id,product_id,url,alt,display_order', 'category:id,name,slug,image'])
            ->whereIn('id', $relatedIds)
            ->where('id', '!=', $productId)
            ->where('status', 'PUBLISHED')
            ->take($limit)
            ->get();
    }

    public function isSkuUnique(string $sku, ?string $excludeId = null): bool
    {
        $query = Product::where('sku', $sku);
        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }
        return !$query->exists();
    }
}
