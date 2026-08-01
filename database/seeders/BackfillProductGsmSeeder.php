<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductAttribute;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;

/**
 * Backfill fabric weight (GSM) + fabric label attributes for products that
 * don't already have a gsm attribute, so the product detail fabric-weight
 * meter shows real values storewide.
 *
 * Category-based realistic values. Products that already have a gsm attribute
 * (set manually via Admin → Products) are skipped — never overwritten.
 *
 * Usage: php artisan db:seed --class=BackfillProductGsmSeeder
 */
class BackfillProductGsmSeeder extends Seeder
{
    private const CATEGORY_FABRIC = [
        "Men's Tees"      => ['gsm' => 240, 'fabric' => '240 GSM Heavyweight Cotton'],
        'Polo Shirts'     => ['gsm' => 220, 'fabric' => '220 GSM Cotton Pique'],
        'Hoodies & Sweats' => ['gsm' => 320, 'fabric' => '320 GSM Fleece Cotton'],
        'Outerwear'       => ['gsm' => 340, 'fabric' => '340 GSM Heavyweight Cotton'],
        'Accessories'     => ['gsm' => 180, 'fabric' => '180 GSM Standard Cotton'],
    ];

    private const DEFAULT_FABRIC = ['gsm' => 240, 'fabric' => '240 GSM Heavyweight Cotton'];

    public function run(): void
    {
        $products = Product::with('category:id,name')->get();

        $filled = 0;
        $skipped = 0;

        $existingGsmIds = ProductAttribute::where('name', 'gsm')->pluck('product_id')->flip();
        $existingFabricIds = ProductAttribute::where('name', 'fabric')->pluck('product_id')->flip();

        foreach ($products as $product) {
            $categoryName = $product->category?->name;
            $spec = self::CATEGORY_FABRIC[$categoryName] ?? self::DEFAULT_FABRIC;

            // Respect admin-set values — only backfill attributes that are missing
            $needGsm = !isset($existingGsmIds[$product->id]);
            $needFabric = !isset($existingFabricIds[$product->id]);

            if (!$needGsm && !$needFabric) {
                $skipped++;
                continue;
            }

            if ($needGsm) {
                ProductAttribute::updateOrCreate(
                    ['product_id' => $product->id, 'name' => 'gsm'],
                    ['value' => (string) $spec['gsm']]
                );
            }
            if ($needFabric) {
                ProductAttribute::updateOrCreate(
                    ['product_id' => $product->id, 'name' => 'fabric'],
                    ['value' => $spec['fabric']]
                );
            }

            $filled++;
        }

        // Invalidate product list/detail caches so the storefront picks up new attributes
        $version = (int) Cache::get('products_cache_version', 0);
        Cache::forever('products_cache_version', $version + 1);
        Cache::forget('homepage_all');
        Cache::forget('app_init');
        Cache::forget('settings_all');

        $this->command->info("BackfillProductGsmSeeder: {$filled} products filled, {$skipped} already had GSM (skipped)");
    }
}
