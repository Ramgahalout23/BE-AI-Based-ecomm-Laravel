<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

/**
 * Attaches sample per-color images to product variants.
 *
 * Safe for existing databases: variants with 2+ images (admin-uploaded
 * galleries) are left untouched. Variants with 0-1 images get upgraded to the
 * full multi-image sample set so the storefront gallery has images to show
 * when a variant is selected.
 *
 * The map doubles as the single source of truth for the per-color thumbnails
 * used by ProductSeeder when creating new variants.
 *
 * Usage: php artisan db:seed --class=VariantThumbnailSeeder
 */
class VariantThumbnailSeeder extends Seeder
{
    /** Sample per-color product thumbnails (Unsplash) shown in the storefront color picker. */
    public const COLOR_IMAGES = [
        'Black'        => 'https://images.unsplash.com/photo-1583743814966-8936f5b7be1a?q=80&w=400',
        'White'        => 'https://images.unsplash.com/photo-1521572163474-6864f9cf17ab?q=80&w=400',
        'Navy'         => 'https://images.unsplash.com/photo-1503342217505-b0a15ec3261c?q=80&w=400',
        'Grey'         => 'https://images.unsplash.com/photo-1576566588028-4147f3842f27?q=80&w=400',
        'Olive'        => 'https://images.unsplash.com/photo-1612714304529-e225036e6c4c?q=80&w=400',
        'Blue'         => 'https://images.unsplash.com/photo-1618354691373-d851c5c3a990?q=80&w=400',
        'Green'        => 'https://images.unsplash.com/photo-1591047139829-d91aecb6caea?q=80&w=400',
        'Burgundy'     => 'https://images.unsplash.com/photo-1556821840-3a63f95609a7?q=80&w=400',
        'Cream'        => 'https://images.unsplash.com/photo-1529374255404-311a2a4f1fd9?q=80&w=400',
        'Dark Wash'    => 'https://images.unsplash.com/photo-1541099649105-f69ad21f3246?q=80&w=400',
        'Medium Wash'  => 'https://images.unsplash.com/photo-1604176354204-9268737828e4?q=80&w=400',
        'Light Wash'   => 'https://images.unsplash.com/photo-1542272604-787c3835535d?q=80&w=400',
    ];

    /** Fallback used for any color not in the map. */
    public const FALLBACK_IMAGE = 'https://images.unsplash.com/photo-1583743814966-8936f5b7be1a?q=80&w=400';

    /**
     * Additional sample garment shots (front/back/detail/model) appended to the
     * per-color thumbnail so every variant gets a small multi-image gallery.
     */
    public const GALLERY_IMAGES = [
        'https://images.unsplash.com/photo-1572495641004-28421ae7c9d2?q=80&w=800',
        'https://images.unsplash.com/photo-1503342217505-b0a15ec3261c?q=80&w=800',
        'https://images.unsplash.com/photo-1552374196-1ab2a1c593e8?q=80&w=800',
        'https://images.unsplash.com/photo-1576566588028-4147f3842f27?q=80&w=800',
        'https://images.unsplash.com/photo-1618354691373-d851c5c3a990?q=80&w=800',
        'https://images.unsplash.com/photo-1521572163474-6864f9cf17ab?q=80&w=800',
        'https://images.unsplash.com/photo-1541099649105-f69ad21f3246?q=80&w=800',
        'https://images.unsplash.com/photo-1604176354204-9268737828e4?q=80&w=800',
    ];

    /**
     * Resolve the sample thumbnail for a color name.
     */
    public static function thumbnailFor(?string $color): string
    {
        if ($color && isset(self::COLOR_IMAGES[$color])) {
            return self::COLOR_IMAGES[$color];
        }
        return self::FALLBACK_IMAGE;
    }

    /**
     * Build a full sample image set for a variant: the per-color thumbnail first
     * (so the color picker keeps its distinct swatch), then 3 more garment shots
     * rotated deterministically by the color name so different colors get
     * different-looking galleries.
     */
    public static function imagesFor(?string $color): array
    {
        $primary = self::thumbnailFor($color);
        $pool = self::GALLERY_IMAGES;
        $offset = $color ? (ord($color[0]) % count($pool)) : 0;
        $extras = [];
        for ($i = 1; $i <= 3; $i++) {
            $extras[] = $pool[($offset + $i) % count($pool)];
        }
        return array_values(array_unique([$primary, ...$extras]));
    }

    /**
     * Run the backfill + repair.
     *
     * Repairs two legacy issues in one pass:
     *  1. Variants whose `attributes` column holds a double-encoded JSON string
     *     (old seeders passed json_encode() into a `json`-cast column) are decoded
     *     back to a proper array so color/size are readable again.
     *  2. Variants with 0-1 images get the full multi-image sample set; variants
     *     with 2+ images (admin-uploaded galleries) are left untouched.
     */
    public function run(): void
    {
        $updated = 0;
        $repaired = 0;

        $products = Product::with('variants')->get();
        foreach ($products as $product) {
            foreach ($product->variants as $variant) {
                $attrs = $variant->attributes;
                $needsRepair = is_string($attrs);

                if ($needsRepair) {
                    $decoded = json_decode($attrs, true);
                    $attrs = is_array($decoded) ? $decoded : [];
                    $repaired++;
                }

                // Upgrade variants with 0-1 images to the full multi-image sample set.
                // Variants with 2+ images (admin-uploaded galleries) are left untouched.
                $existing = is_array($variant->images) ? $variant->images : [];
                if (!$needsRepair && count($existing) >= 2) {
                    continue;
                }

                $color = $attrs['color'] ?? null;
                $variant->update([
                    'attributes' => $attrs,
                    'images' => self::imagesFor($color),
                ]);
                $updated++;
            }
        }

        // Invalidate product caches so the storefront reflects the repaired data immediately
        \Illuminate\Support\Facades\Cache::increment('products_cache_version');
        \Illuminate\Support\Facades\Cache::forget('homepage_all');

        $this->command->info("✓ VariantThumbnailSeeder: repaired {$repaired} variant(s) with string attributes, attached images to {$updated} variant(s).");
    }
}
