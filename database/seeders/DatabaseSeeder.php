<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use App\Models\User;
use App\Models\Category;
use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\Banner;
use App\Models\Setting;
use App\Models\Page;
use App\Models\Coupon;
use App\Models\Review;
use App\Models\ShippingZone;
use App\Models\ShippingRate;
use App\Models\VipTier;
use App\Models\Address;
// use DB facade already imported above
use App\Models\Subscriber;
use App\Models\Campaign;
use App\Models\Seo;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Shipping;
use App\Models\OrderTimeline;
use App\Models\CouponAnalytics;
use App\Models\CartItem;
use App\Models\WishlistItem;
use App\Models\Discount;
use App\Models\Inventory;
use App\Models\Wallet;
use App\Models\LoyaltyPoint;
use App\Models\ActivityLog;
use App\Models\Sitemap;
use App\Models\RobotsTxt;
use App\Models\CuratedLook;
use App\Models\Reel;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run()
    {
        // =====================
        // Clear existing data
        // =====================
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        ActivityLog::truncate();
        CartItem::truncate();
        WishlistItem::truncate();
        OrderTimeline::truncate();
        OrderItem::truncate();
        Payment::truncate();
        Shipping::truncate();
        Order::truncate();
        ProductVariant::truncate();
        ProductImage::truncate();
        Inventory::truncate();
        Product::truncate();
        Category::truncate();
        Brand::truncate();
        Banner::truncate();
        Page::truncate();
        Setting::truncate();
        Coupon::truncate();
        CouponAnalytics::truncate();
        Review::truncate();
        Discount::truncate();
        ShippingZone::truncate();
        ShippingRate::truncate();
        VipTier::truncate();
        Address::truncate();
        DB::table('notifications')->truncate();
        Wallet::truncate();
        LoyaltyPoint::truncate();
        Subscriber::truncate();
        Campaign::truncate();
        Seo::truncate();
        Sitemap::truncate();
        RobotsTxt::truncate();
        CuratedLook::truncate();
        Reel::truncate();
        User::where('email', '!=', 'admin@threvolt.com')->delete();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $this->command->info('🗑️  Existing data cleared');

        // =====================
        // 1. CREATE ADMIN USER
        // =====================
        $this->command->info('👤 Creating admin user...');
        $admin = User::updateOrCreate(
            ['email' => 'admin@threvolt.com'],
            [
                'first_name' => 'Admin',
                'last_name' => 'THREVOLT',
                'password' => Hash::make('Admin@123'),
                'phone_number' => '+91 98765 43210',
                'role' => 'ADMIN',
                'is_email_verified' => true,
                'is_active' => true,
            ]
        );
        $this->command->info('   ✓ Admin: admin@threvolt.com / Admin@123');

        // =====================
        // 2. CREATE CATEGORIES
        // =====================
        $this->command->info('📂 Seeding categories...');
        $catData = [
            ['name' => 'Legendary Series', 'slug' => 'legendary-series', 'description' => 'Premium t-shirt collections with iconic designs — made for legends.', 'image' => 'https://images.unsplash.com/photo-1503342217505-b0a15ec3261c?q=80&w=400'],
            ['name' => 'Official Merchandise', 'slug' => 'official-merchandise', 'description' => 'Licensed and official merchandise — movies, music, sports & more.', 'image' => 'https://images.unsplash.com/photo-1529374255404-311a2a4f1fd9?q=80&w=400'],
            ['name' => 'Oversized Collection', 'slug' => 'oversized-collection', 'description' => 'Premium oversized fit t-shirts — drop shoulder, boxy & classic cuts.', 'image' => 'https://images.unsplash.com/photo-1583743814966-8936f5b7be1a?q=80&w=400'],
            ['name' => 'Accessories', 'slug' => 'accessories', 'description' => 'Complete your look — caps, bags, phone cases & more.', 'image' => 'https://images.unsplash.com/photo-1523381210434-271e8be1f52b?q=80&w=400'],
        ];
        $categories = [];
        foreach ($catData as $c) {
            $cat = Category::create($c + ['seo_title' => $c['name'].' | THREVOLT', 'seo_description' => $c['description'], 'is_active' => true]);
            $categories[$c['slug']] = $cat->id;
        }

        // Subcategories
        $subData = [
            ['parent' => 'legendary-series', 'children' => [
                ['name' => "Men's Legendary", 'slug' => 'mens-legendary'],
                ['name' => "Women's Legendary", 'slug' => 'womens-legendary'],
                ['name' => 'Unisex Legendary', 'slug' => 'unisex-legendary'],
            ]],
            ['parent' => 'official-merchandise', 'children' => [
                ['name' => 'Movie Merchandise', 'slug' => 'movie-merchandise'],
                ['name' => 'Music Merchandise', 'slug' => 'music-merchandise'],
                ['name' => 'Sports Merchandise', 'slug' => 'sports-merchandise'],
            ]],
            ['parent' => 'oversized-collection', 'children' => [
                ['name' => 'Drop Shoulder', 'slug' => 'drop-shoulder'],
                ['name' => 'Boxy Fit', 'slug' => 'boxy-fit'],
                ['name' => 'Classic Oversized', 'slug' => 'classic-oversized'],
            ]],
            ['parent' => 'accessories', 'children' => [
                ['name' => 'Caps', 'slug' => 'caps'],
                ['name' => 'Bags', 'slug' => 'bags'],
                ['name' => 'Phone Cases', 'slug' => 'phone-cases'],
            ]],
        ];
        foreach ($subData as $group) {
            foreach ($group['children'] as $child) {
                Category::create([
                    'name' => $child['name'],
                    'slug' => $child['slug'],
                    'description' => $child['name'].' — part of '.$group['parent'],
                    'parent_id' => $categories[$group['parent']],
                    'seo_title' => $child['name'].' | THREVOLT',
                    'is_active' => true,
                ]);
            }
        }
        $this->command->info('   ✓ Categories + Subcategories created');

        // =====================
        // 3. CREATE BRANDS
        // =====================
        $this->command->info('🏷️  Seeding brands...');
        $brandData = [
            ['name' => 'THREVOLT', 'slug' => 'threvolt', 'logo' => 'https://images.unsplash.com/photo-1583743814966-8936f5b7be1a?q=80&w=200', 'description' => 'In-house premium brand'],
            ['name' => 'Urban Threads', 'slug' => 'urban-threads', 'logo' => 'https://images.unsplash.com/photo-1521572163474-6864f9cf17ab?q=80&w=200', 'description' => 'Streetwear essentials'],
            ['name' => 'NeoPrint', 'slug' => 'neo-print', 'logo' => 'https://images.unsplash.com/photo-1503341504253-dff4815485f1?q=80&w=200', 'description' => 'Graphic prints specialist'],
        ];
        $brands = [];
        foreach ($brandData as $b) {
            $brand = Brand::create($b);
            $brands[$b['slug']] = $brand->id;
        }
        $this->command->info('   ✓ Brands created');

        // =====================
        // 4. CREATE PRODUCTS
        // =====================
        $this->command->info('📦 Seeding products...');
        $products = [];

        $productData = [
            // Legendary Series
            ['name' => 'Urban Oversized Tee — Black', 'slug' => 'urban-oversized-tee-black', 'price' => 599, 'old_price' => 999, 'category' => 'legendary-series', 'image' => 'https://images.unsplash.com/photo-1583743814966-8936f5b7be1a?q=80&w=800', 'hover' => 'https://images.unsplash.com/photo-1572495641004-28421ae7c9d2?q=80&w=800', 'rating' => 4.8, 'review_count' => 2340, 'badge' => 'Bestseller', 'featured' => true, 'desc' => 'Premium 240 GSM cotton oversized tee. Drop shoulder. Made for legends. Ultra-comfortable streetwear essential with a relaxed fit.'],
            ['name' => 'Abstract Art Graphic Tee', 'slug' => 'abstract-art-graphic-tee', 'price' => 499, 'old_price' => 799, 'category' => 'legendary-series', 'image' => 'https://images.unsplash.com/photo-1503342217505-b0a15ec3261c?q=80&w=800', 'hover' => 'https://images.unsplash.com/photo-1583743814966-8936f5b7be1a?q=80&w=800', 'rating' => 4.7, 'review_count' => 1856, 'badge' => 'Hot', 'featured' => true, 'desc' => 'Custom abstract graphic print on premium ringspun cotton. A true legend in the making.'],
            ['name' => 'Essential Plain Tee — White', 'slug' => 'essential-plain-tee-white', 'price' => 349, 'old_price' => 599, 'category' => 'legendary-series', 'image' => 'https://images.unsplash.com/photo-1521572163474-6864f9cf17ab?q=80&w=800', 'hover' => 'https://images.unsplash.com/photo-1618354691373-d851c5c3a990?q=80&w=800', 'rating' => 4.5, 'review_count' => 3200, 'badge' => 'Value', 'featured' => true, 'desc' => '180 GSM pure cotton basic tee. Everyday essential — a legendary staple.'],
            ['name' => 'Tokyo Streetwear Graphic Tee', 'slug' => 'tokyo-streetwear-graphic-tee', 'price' => 549, 'old_price' => 899, 'category' => 'legendary-series', 'image' => 'https://images.unsplash.com/photo-1576566588028-4147f3842f27?q=80&w=800', 'hover' => 'https://images.unsplash.com/photo-1612714304529-e225036e6c4c?q=80&w=800', 'rating' => 4.9, 'review_count' => 1500, 'badge' => 'Trending', 'featured' => true, 'desc' => 'HD DTF print on heavyweight cotton featuring Neo-Tokyo aesthetic. Anime-inspired graphics.'],
            ['name' => 'Minimal Logo Tee — Navy', 'slug' => 'minimal-logo-tee-navy', 'price' => 449, 'old_price' => 749, 'category' => 'legendary-series', 'image' => 'https://images.unsplash.com/photo-1612714304529-e225036e6c4c?q=80&w=800', 'hover' => 'https://images.unsplash.com/photo-1552374196-1ab2a1c593e8?q=80&w=800', 'rating' => 4.6, 'review_count' => 890, 'badge' => 'New', 'featured' => true, 'desc' => 'Clean minimal branding on premium 220 GSM cotton. Understated elegance.'],

            // Official Merchandise
            ['name' => 'Classic Polo — Forest Green', 'slug' => 'classic-polo-forest-green', 'price' => 699, 'old_price' => 1199, 'category' => 'official-merchandise', 'image' => 'https://images.unsplash.com/photo-1581655353564-df123a1eb820?q=80&w=800', 'hover' => 'https://images.unsplash.com/photo-1529374255404-311a2a4f1fd9?q=80&w=800', 'rating' => 4.6, 'review_count' => 890, 'badge' => 'New', 'featured' => false, 'desc' => 'Pique cotton polo with ribbed collar. Premium casual-formal style.'],
            ['name' => 'Pack of 3 — Plain Basics', 'slug' => 'pack-of-3-plain-basics', 'price' => 899, 'old_price' => 1497, 'category' => 'official-merchandise', 'image' => 'https://images.unsplash.com/photo-1618354691373-d851c5c3a990?q=80&w=800', 'hover' => 'https://images.unsplash.com/photo-1521572163474-6864f9cf17ab?q=80&w=800', 'rating' => 4.8, 'review_count' => 5600, 'badge' => 'Best Value', 'featured' => true, 'desc' => 'Black + White + Grey combo. Save 62% on premium cotton basics.'],
            ['name' => 'Striped Campus Tee — Blue', 'slug' => 'striped-campus-tee-blue', 'price' => 499, 'old_price' => 849, 'category' => 'official-merchandise', 'image' => 'https://images.unsplash.com/photo-1596755094514-f87e34085b2c?q=80&w=800', 'hover' => 'https://images.unsplash.com/photo-1503342217505-b0a15ec3261c?q=80&w=800', 'rating' => 4.4, 'review_count' => 720, 'badge' => null, 'featured' => false, 'desc' => 'Classic stripe pattern on soft cotton. Perfect for college and casual outings.'],

            // Oversized Collection
            ['name' => 'Vintage Acid Wash Tee', 'slug' => 'vintage-acid-wash-tee', 'price' => 799, 'old_price' => 1299, 'category' => 'oversized-collection', 'image' => 'https://images.unsplash.com/photo-1529374255404-311a2a4f1fd9?q=80&w=800', 'hover' => 'https://images.unsplash.com/photo-1581655353564-df123a1eb820?q=80&w=800', 'rating' => 4.4, 'review_count' => 620, 'badge' => 'Limited', 'featured' => true, 'desc' => 'Unique acid wash finish in oversized cut. No two tees are the same!'],
            ['name' => 'Full Sleeve Henley — Maroon', 'slug' => 'full-sleeve-henley-maroon', 'price' => 649, 'old_price' => 999, 'category' => 'oversized-collection', 'image' => 'https://images.unsplash.com/photo-1563630423918-b58f07336ac9?q=80&w=800', 'hover' => 'https://images.unsplash.com/photo-1572495641004-28421ae7c9d2?q=80&w=800', 'rating' => 4.6, 'review_count' => 410, 'badge' => 'New', 'featured' => false, 'desc' => 'Soft cotton henley with 3-button placket. Relaxed oversized fit.'],
            ['name' => 'Drop Shoulder Oversized — Olive', 'slug' => 'drop-shoulder-oversized-olive', 'price' => 749, 'old_price' => 1199, 'category' => 'oversized-collection', 'image' => 'https://images.unsplash.com/photo-1583743814966-8936f5b7be1a?q=80&w=800', 'hover' => 'https://images.unsplash.com/photo-1576566588028-4147f3842f27?q=80&w=800', 'rating' => 4.7, 'review_count' => 340, 'badge' => 'Trending', 'featured' => true, 'desc' => 'Trending drop shoulder design in premium olive tone. Boxy cut.'],

            // Accessories
            ['name' => 'Premium Cap — Classic Black', 'slug' => 'premium-cap-classic-black', 'price' => 399, 'old_price' => 699, 'category' => 'accessories', 'image' => 'https://images.unsplash.com/photo-1523381210434-271e8be1f52b?q=80&w=800', 'hover' => 'https://images.unsplash.com/photo-1556306535-0f09a537bee0?q=80&w=800', 'rating' => 4.5, 'review_count' => 1200, 'badge' => 'Bestseller', 'featured' => true, 'desc' => 'High-quality structured cap with embroidered logo. Adjustable fit.'],
            ['name' => 'Canvas Tote Bag — Natural', 'slug' => 'canvas-tote-bag-natural', 'price' => 349, 'old_price' => 599, 'category' => 'accessories', 'image' => 'https://images.unsplash.com/photo-1544816155-12df9643f363?q=80&w=800', 'hover' => 'https://images.unsplash.com/photo-1523381210434-271e8be1f52b?q=80&w=800', 'rating' => 4.3, 'review_count' => 560, 'badge' => 'Eco', 'featured' => false, 'desc' => 'Heavy-duty canvas tote with printed logo. Eco-friendly and durable.'],
            ['name' => 'Phone Case — Impact Series', 'slug' => 'phone-case-impact-series', 'price' => 299, 'old_price' => 499, 'category' => 'accessories', 'image' => 'https://images.unsplash.com/photo-1601784551446-20c9e07cdbdb?q=80&w=800', 'hover' => 'https://images.unsplash.com/photo-1592750475338-74b7b21085ab?q=80&w=800', 'rating' => 4.2, 'review_count' => 890, 'badge' => 'Value', 'featured' => false, 'desc' => 'Military-grade drop protection with sleek design. Fits all major phone models.'],

            // Test OOS Products
            ['name' => 'Classic Crew Neck Tee — OOS Test', 'slug' => 'classic-crew-neck-oos-test', 'price' => 399, 'old_price' => 699, 'category' => 'legendary-series', 'image' => 'https://images.unsplash.com/photo-1521572163474-6864f9cf17ab?q=80&w=800', 'hover' => 'https://images.unsplash.com/photo-1618354691373-d851c5c3a990?q=80&w=800', 'rating' => 4.0, 'review_count' => 0, 'badge' => null, 'featured' => true, 'desc' => 'TEST — Zero stock item to verify OOS display.', 'qty' => 0],

            // Mixed Variant Stock Test — Size S OOS, Size M in stock
            ['name' => 'Mixed Variant Stock — Test Product', 'slug' => 'mixed-variant-stock-test', 'price' => 499, 'old_price' => 799, 'category' => 'legendary-series', 'image' => 'https://images.unsplash.com/photo-1596755094514-f87e34085b2c?q=80&w=800', 'hover' => 'https://images.unsplash.com/photo-1503342217505-b0a15ec3261c?q=80&w=800', 'rating' => 4.0, 'review_count' => 0, 'badge' => null, 'featured' => true, 'desc' => 'TEST — Mixed variant stock: Size S=OOS, Size M=In Stock, Size L=In Stock, Size XL=OOS, Size XXL=In Stock. For verifying variant-level OOS display and E2E testing.', 'qty' => 30,
                'color_config' => [['color' => 'Black', 'price' => 499], ['color' => 'White', 'price' => 499]],
                'variant_override' => [
                    ['size' => 'S',   'color' => 'Black', 'qty' => 0],
                    ['size' => 'M',   'color' => 'Black', 'qty' => 10],
                    ['size' => 'L',   'color' => 'Black', 'qty' => 8],
                    ['size' => 'XL',  'color' => 'Black', 'qty' => 0],
                    ['size' => 'XXL', 'color' => 'Black', 'qty' => 5],
                    ['size' => 'S',   'color' => 'White', 'qty' => 0],
                    ['size' => 'M',   'color' => 'White', 'qty' => 12],
                    ['size' => 'L',   'color' => 'White', 'qty' => 6],
                    ['size' => 'XL',  'color' => 'White', 'qty' => 0],
                    ['size' => 'XXL', 'color' => 'White', 'qty' => 4],
                ],
            ],
        ];

        $products = []; // [ ['model' => Product, 'data' => [...original array...]] ]
        foreach ($productData as $i => $p) {
            $prod = Product::create([
                'name' => $p['name'],
                'slug' => $p['slug'],
                'description' => $p['desc'],
                'short_description' => substr($p['desc'], 0, 100),
                'price' => $p['price'],
                'old_price' => $p['old_price'],
                'cost' => round($p['price'] * 0.4, 2),
                'quantity' => $p['qty'] ?? 100,
                'sku' => strtoupper(substr($p['category'], 0, 3)).'-'.str_pad($i+1, 3, '0', STR_PAD_LEFT),
                'category_id' => $categories[$p['category']],
                'brand_id' => $brands['threvolt'],
                'status' => 'PUBLISHED',
                'is_featured' => $p['featured'],
                'badge' => $p['badge'],
                'rating' => $p['rating'],
                'review_count' => $p['review_count'],
                'seo_title' => $p['name'].' | THREVOLT',
                'seo_description' => $p['desc'],
            ]);

            // Product images
            ProductImage::create(['product_id' => $prod->id, 'url' => $p['image'], 'alt' => $p['name'], 'display_order' => 1]);
            ProductImage::create(['product_id' => $prod->id, 'url' => $p['hover'], 'alt' => $p['name'].' - Alternate', 'display_order' => 2]);

            // Inventory
            Inventory::create([
                'product_id' => $prod->id,
                'total_quantity' => $p['qty'] ?? 100,
                'available_quantity' => $p['qty'] ?? 100,
                'reserved_quantity' => 0,
                'damaged_quantity' => 0,
            ]);

            $products[] = ['model' => $prod, 'data' => $p];
        }
        $this->command->info('   ✓ '.count($productData).' products created');

        // =====================
        // 5. PRODUCT VARIANTS
        // =====================
        $this->command->info('📦 Creating product variants...');
        $sizes = ['S', 'M', 'L', 'XL', 'XXL'];
        $colorConfigs = [
            'Urban Oversized Tee — Black' => [
                ['color' => 'Black', 'price' => 599], ['color' => 'White', 'price' => 599],
                ['color' => 'Navy', 'price' => 599], ['color' => 'Grey', 'price' => 649], ['color' => 'Olive', 'price' => 649],
            ],
            'Abstract Art Graphic Tee' => [
                ['color' => 'White', 'price' => 499], ['color' => 'Black', 'price' => 499], ['color' => 'Grey', 'price' => 549],
            ],
            'Essential Plain Tee — White' => [
                ['color' => 'White', 'price' => 349], ['color' => 'Black', 'price' => 349],
                ['color' => 'Grey', 'price' => 349], ['color' => 'Navy', 'price' => 399],
            ],
            'Tokyo Streetwear Graphic Tee' => [
                ['color' => 'Black', 'price' => 549], ['color' => 'White', 'price' => 549],
            ],
            'Vintage Acid Wash Tee' => [
                ['color' => 'Blue Wash', 'price' => 799], ['color' => 'Grey Wash', 'price' => 799],
            ],
            'Classic Polo — Forest Green' => [
                ['color' => 'Green', 'price' => 699], ['color' => 'Navy', 'price' => 699], ['color' => 'White', 'price' => 699],
            ],
            'Full Sleeve Henley — Maroon' => [
                ['color' => 'Maroon', 'price' => 649], ['color' => 'Black', 'price' => 649], ['color' => 'Navy', 'price' => 699],
            ],
        ];

        $sizes = ['S', 'M', 'L', 'XL', 'XXL'];

        foreach ($products as $entry) {
            $prod = $entry['model'];
            $data = $entry['data'];

            // Check if product has a deterministic variant override (for E2E test products)
            if (!empty($data['variant_override']) && is_array($data['variant_override'])) {
                foreach ($data['variant_override'] as $v) {
                    $variantName = $prod->name.' - '.$v['color'].' - '.$v['size'];
                    ProductVariant::create([
                        'product_id' => $prod->id,
                        'name' => $variantName,
                        'sku' => $prod->sku.'-'.strtoupper(substr($v['color'], 0, 3)).'-'.$v['size'],
                        'attributes' => json_encode(['size' => $v['size'], 'color' => $v['color']]),
                        'price' => $v['price'] ?? $prod->price,
                        'quantity' => $v['qty'],
                    ]);
                }
                continue;
            }

            if (!isset($colorConfigs[$prod->name])) continue;
            foreach ($colorConfigs[$prod->name] as $config) {
                foreach ($sizes as $size) {
                    $variantName = $prod->name.' - '.$config['color'].' - '.$size;
                    ProductVariant::create([
                        'product_id' => $prod->id,
                        'name' => $variantName,
                        'sku' => $prod->sku.'-'.strtoupper(substr($config['color'], 0, 3)).'-'.$size,
                        'attributes' => json_encode(['size' => $size, 'color' => $config['color']]),
                        'price' => $config['price'],
                        'quantity' => rand(8, 20),
                    ]);
                }
            }
        }
        $this->command->info('   ✓ Product variants created');

        // =====================
        // 6. CREATE BANNERS
        // =====================
        $this->command->info('🖼️  Seeding banners...');
        $banners = [
            [
                'title' => "IN TREND\nOVERSIZED TEE", 'subtitle' => 'GET 10% OFF on your first order — use code WELCOME20',
                'tagline' => 'Featured', 'description' => 'Premium 240 GSM cotton oversized tee. Drop shoulder. Made for legends.',
                'image_url' => 'https://images.unsplash.com/photo-1552374196-1ab2a1c593e8?q=80&w=2000',
                'link_url' => '/products?category=oversized-collection', 'type' => 'HERO', 'position' => 1,
                'button_text' => 'Shop Now', 'button_link' => '/products?category=oversized-collection', 'cta' => 'Shop Now',
                'align' => 'left', 'text_dark' => false,
            ],
            [
                'title' => "FLAT 50% OFF\nGRAPHIC TEES", 'subtitle' => 'Anime, vintage, abstract — express yourself without saying a word.',
                'tagline' => 'Limited Time', 'description' => 'Limited time offer on selected items',
                'image_url' => 'https://images.unsplash.com/photo-1529374255404-311a2a4f1fd9?q=80&w=2000',
                'link_url' => '/products?category=legendary-series', 'type' => 'HERO', 'position' => 2,
                'button_text' => 'Shop Now', 'button_link' => '/products?category=legendary-series', 'cta' => 'Shop Now',
                'align' => 'center', 'text_dark' => false,
            ],
            [
                'title' => "NEW DROP\nPREMIUM POLO", 'subtitle' => 'Pique cotton. Ribbed collar. Classic comfort redefined.',
                'tagline' => 'Just Launched', 'description' => 'Pique cotton polo with ribbed collar. Premium casual-formal style.',
                'image_url' => 'https://images.unsplash.com/photo-1581655353564-df123a1eb820?q=80&w=2000',
                'link_url' => '/products?category=official-merchandise', 'type' => 'HERO', 'position' => 3,
                'button_text' => 'Shop Now', 'button_link' => '/products?category=official-merchandise', 'cta' => 'Shop Now',
                'align' => 'right', 'text_dark' => false,
            ],
            [
                'title' => 'Flash Sale - Up to 70% Off', 'description' => 'Limited time offer on selected items',
                'image_url' => 'https://images.unsplash.com/photo-1618354691373-d851c5c3a990?q=80&w=1200',
                'link_url' => '/products?sale=true', 'type' => 'SALE', 'position' => 1,
                'button_text' => 'Shop Now', 'button_link' => '/products?sale=true',
            ],
        ];

        foreach ($banners as $b) {
            Banner::create(array_merge($b, ['is_active' => true, 'start_date' => now(), 'end_date' => now()->addYear()]));
        }
        $this->command->info('   ✓ Banners created');

        // =====================
        // 7. CREATE SETTINGS
        // =====================
        $this->command->info('⚙️  Seeding settings...');
        $settings = [
            // Store Info
            ['module' => 'SITE', 'key' => 'storeName', 'value' => 'THREVOLT'],
            ['module' => 'SITE', 'key' => 'contactEmail', 'value' => 'support@threvolt.com'],
            ['module' => 'SITE', 'key' => 'storeAddress', 'value' => 'Bangalore, Karnataka, India'],
            ['module' => 'SITE', 'key' => 'contactPhone', 'value' => '+91 98765 43210'],
            ['module' => 'SITE', 'key' => 'currency', 'value' => 'INR'],
            ['module' => 'SITE', 'key' => 'timezone', 'value' => 'IST'],

            // Shipping
            ['module' => 'SHIPPING', 'key' => 'freeShippingThreshold', 'value' => '499'],
            ['module' => 'SHIPPING', 'key' => 'shippingFlatRate', 'value' => '50'],
            ['module' => 'SHIPPING', 'key' => 'shippingPickupAddress', 'value' => 'THREVOLT Fulfillment Center, Bangalore, Karnataka, India'],
            ['module' => 'SHIPPING', 'key' => 'shippingReturnAddress', 'value' => 'THREVOLT Returns, Bangalore, Karnataka, India'],

            // Tax
            ['module' => 'TAX', 'key' => 'taxRate', 'value' => '18.0'],
            ['module' => 'TAX', 'key' => 'taxCalculation', 'value' => 'inclusive'],

            // Maintenance
            ['module' => 'SITE', 'key' => 'maintenanceMode', 'value' => 'false'],
            ['module' => 'SITE', 'key' => 'maintenanceMessage', 'value' => 'We are currently under maintenance. Please check back soon.'],

            // SMTP
            ['module' => 'SMTP', 'key' => 'fromEmailAddress', 'value' => 'support@threvolt.com'],
            ['module' => 'SMTP', 'key' => 'emailTemplate', 'value' => 'default'],

            // WebSocket
            ['module' => 'WEBSOCKET', 'key' => 'socketEnabled', 'value' => 'true'],
            ['module' => 'WEBSOCKET', 'key' => 'socketPingInterval', 'value' => '25000'],
            ['module' => 'WEBSOCKET', 'key' => 'socketPingTimeout', 'value' => '10000'],
            ['module' => 'WEBSOCKET', 'key' => 'socketAllowedOrigins', 'value' => 'http://localhost:3000,http://localhost:5173'],

            // Ads Settings
            ['module' => 'ADS', 'key' => 'metaAccessToken', 'value' => ''],
            ['module' => 'ADS', 'key' => 'metaAdAccountId', 'value' => ''],
            ['module' => 'ADS', 'key' => 'whatsappAccessToken', 'value' => ''],
            ['module' => 'ADS', 'key' => 'googleAdsClientId', 'value' => ''],
            ['module' => 'ADS', 'key' => 'googleAdsDeveloperToken', 'value' => ''],

            // Social Login
            ['module' => 'SITE', 'key' => 'googleClientId', 'value' => ''],
            ['module' => 'SITE', 'key' => 'facebookAppId', 'value' => ''],

            // OpenAI
            ['module' => 'SITE', 'key' => 'openaiApiKey', 'value' => ''],

            // Razorpay
            ['module' => 'PAYMENT', 'key' => 'razorpayEnabled', 'value' => 'true'],
            ['module' => 'PAYMENT', 'key' => 'razorpayKeyId', 'value' => 'rzp_test_xxxxxxxx'],
            ['module' => 'PAYMENT', 'key' => 'razorpayKeySecret', 'value' => ''],

            // COD
            ['module' => 'PAYMENT', 'key' => 'codEnabled', 'value' => 'true'],
            ['module' => 'PAYMENT', 'key' => 'codInstructions', 'value' => 'Pay with cash upon delivery'],
        ];

        foreach ($settings as $s) {
            Setting::updateOrCreate(
                ['module' => $s['module'], 'key' => $s['key']],
                ['value' => $s['value']]
            );
        }
        $this->command->info('   ✓ '.count($settings).' settings created');

        // =====================
        // 8. CREATE CMS PAGES
        // =====================
        $this->command->info('📄 Seeding CMS pages...');
        Page::create([
            'title' => 'About Us',
            'slug' => 'about',
            'content' => json_encode([
                'heroTitle' => 'About THREVOLT',
                'heroSubtitle' => "India's boldest t-shirt brand.",
                'storyTitle' => 'Our Story',
                'storyContent' => ['THREVOLT started with a simple mission — create t-shirts that make a statement.'],
            ]),
            'meta_title' => 'About THREVOLT',
            'meta_description' => 'Learn about THREVOLT.',
            'is_published' => true,
        ]);
        Page::create([
            'title' => 'Privacy Policy',
            'slug' => 'privacy-policy',
            'content' => json_encode([
                'lastUpdated' => 'May 2026',
                'sections' => [
                    ['title' => '1. Information We Collect', 'content' => 'We collect information you provide when creating an account.'],
                    ['title' => '2. How We Use Your Information', 'content' => 'Your information is used to process orders.'],
                ],
            ]),
            'meta_title' => 'Privacy Policy - THREVOLT',
            'meta_description' => 'THREVOLT Privacy Policy.',
            'is_published' => true,
        ]);
        Page::create([
            'title' => 'Return & Exchange Policy',
            'slug' => 'return-policy',
            'content' => json_encode([
                'heroTitle' => 'Return & Exchange Policy',
                'heroSubtitle' => "We want you to love your purchase.",
                'quickStats' => [
                    ['icon' => 'refresh', 'title' => '7-Day Returns', 'desc' => 'Return within 7 days'],
                    ['icon' => 'truck', 'title' => 'Free Pickup', 'desc' => 'We pick up for free'],
                    ['icon' => 'shield', 'title' => '100% Refund', 'desc' => 'Full refund'],
                ],
            ]),
            'meta_title' => 'Return Policy - THREVOLT',
            'meta_description' => 'Easy returns and exchanges.',
            'is_published' => true,
        ]);
        Page::create([
            'title' => 'Contact Us',
            'slug' => 'contact',
            'content' => json_encode([
                'email' => 'support@threvolt.com',
                'phone' => '+91 98765 43210',
                'address' => 'Bangalore, Karnataka, India',
                'hours' => 'Mon-Sat, 9AM-7PM',
            ]),
            'meta_title' => 'Contact Us - THREVOLT',
            'meta_description' => 'Get in touch.',
            'is_published' => true,
        ]);
        $this->command->info('   ✓ CMS pages created');

        // =====================
        // 9. CREATE SAMPLE CUSTOMERS
        // =====================
        $this->command->info('👥 Creating sample customers...');
        $customerPwd = Hash::make('Demo@123');

        $customer1 = User::updateOrCreate(
            ['email' => 'customer@threvolt.com'],
            [
                'first_name' => 'Demo', 'last_name' => 'Customer',
                'password' => $customerPwd,
                'phone_number' => '+91 98765 43210', 'role' => 'CUSTOMER',
                'is_email_verified' => true, 'is_active' => true,
            ]
        );
        $this->command->info('   ✓ customer@threvolt.com / Demo@123');

        $users = [$customer1];
        $extraUsers = [
            ['first_name' => 'Rahul', 'last_name' => 'Sharma', 'email' => 'rahul.sharma@email.com', 'phone' => '+91 98765 12345'],
            ['first_name' => 'Priya', 'last_name' => 'Patel', 'email' => 'priya.patel@email.com', 'phone' => '+91 99887 76655'],
            ['first_name' => 'Amit', 'last_name' => 'Kumar', 'email' => 'amit.kumar@email.com', 'phone' => '+91 91234 56789'],
        ];

        foreach ($extraUsers as $u) {
            $user = User::updateOrCreate(
                ['email' => $u['email']],
                [
                    'first_name' => $u['first_name'], 'last_name' => $u['last_name'],
                    'password' => $customerPwd,
                    'phone_number' => $u['phone'], 'role' => 'CUSTOMER',
                    'is_email_verified' => true, 'is_active' => true,
                ]
            );
            $users[] = $user;
        }

        // Addresses
        Address::create(['user_id' => $customer1->id, 'type' => 'HOME', 'first_name' => 'Demo', 'last_name' => 'Customer',
            'phone_number' => '+91 98765 43210', 'address_line1' => '123, MG Road', 'city' => 'Bangalore',
            'state' => 'Karnataka', 'zip_code' => '560001', 'country' => 'India', 'is_default' => true]);

        Address::create(['user_id' => $users[1]->id, 'type' => 'HOME', 'first_name' => 'Rahul', 'last_name' => 'Sharma',
            'phone_number' => '+91 98765 12345', 'address_line1' => '123, MG Road', 'address_line2' => 'Apartment 4B',
            'city' => 'Bangalore', 'state' => 'Karnataka', 'zip_code' => '560001', 'country' => 'India', 'is_default' => true]);

        Address::create(['user_id' => $users[2]->id, 'type' => 'HOME', 'first_name' => 'Priya', 'last_name' => 'Patel',
            'phone_number' => '+91 99887 76655', 'address_line1' => '78, Navrangpura',
            'city' => 'Ahmedabad', 'state' => 'Gujarat', 'zip_code' => '380009', 'country' => 'India', 'is_default' => true]);

        Address::create(['user_id' => $users[3]->id, 'type' => 'HOME', 'first_name' => 'Amit', 'last_name' => 'Kumar',
            'phone_number' => '+91 91234 56789', 'address_line1' => '45, Connaught Place',
            'city' => 'New Delhi', 'state' => 'Delhi', 'zip_code' => '110001', 'country' => 'India', 'is_default' => true]);
        $this->command->info('   ✓ Addresses created');

        // =====================
        // 10. VIP TIERS
        // =====================
        $this->command->info('👑 Seeding VIP tiers...');
        VipTier::create(['name' => 'Bronze', 'min_points' => 0, 'benefits' => '5% off on all orders']);
        VipTier::create(['name' => 'Silver', 'min_points' => 500, 'benefits' => '10% off + free shipping']);
        VipTier::create(['name' => 'Gold', 'min_points' => 1500, 'benefits' => '15% off + early access + free shipping']);
        VipTier::create(['name' => 'Platinum', 'min_points' => 5000, 'benefits' => '20% off + priority support + exclusive deals']);

        // Wallets & Loyalty
        foreach ($users as $user) {
            Wallet::create(['user_id' => $user->id, 'balance' => rand(100, 2000), 'created_at' => now(), 'updated_at' => now()]);
            LoyaltyPoint::create(['user_id' => $user->id, 'points' => rand(50, 500), 'created_at' => now(), 'updated_at' => now()]);
        }
        $this->command->info('   ✓ VIP tiers, wallets & loyalty created');

        // =====================
        // 11. COUPONS
        // =====================
        $this->command->info('🎫 Seeding coupons...');
        $couponList = [
            ['code' => 'WELCOME20', 'type' => 'PERCENTAGE', 'discount_type' => 'PERCENTAGE', 'discount_value' => 20, 'min_order_value' => 499, 'is_new_user_only' => true],
            ['code' => 'FLAT300', 'type' => 'FIXED', 'discount_type' => 'FLAT', 'discount_value' => 300, 'min_order_value' => 999],
            ['code' => 'FREESHIP', 'type' => 'FREE_SHIPPING', 'discount_type' => 'FLAT', 'discount_value' => 0, 'min_order_value' => 499, 'is_auto_apply' => true],
            ['code' => 'SAVE30', 'type' => 'PERCENTAGE', 'discount_type' => 'PERCENTAGE', 'discount_value' => 30, 'max_discount' => 500, 'is_stackable' => true],
            ['code' => 'FIRST50', 'type' => 'FIRST_ORDER', 'discount_type' => 'PERCENTAGE', 'discount_value' => 50, 'max_discount' => 200, 'is_new_user_only' => true],
        ];

        foreach ($couponList as $c) {
            $coupon = Coupon::create(array_merge($c, [
                'start_date' => now(), 'expiry_date' => now()->addYear(),
                'is_active' => true,
            ]));
            CouponAnalytics::create(['coupon_id' => $coupon->id]);
        }
        $this->command->info('   ✓ Coupons created');

        // =====================
        // 12. REVIEWS
        // =====================
        $this->command->info('⭐ Seeding reviews...');
        $reviewData = [
            ['rating' => 5, 'title' => 'Amazing quality!', 'comment' => 'Fabric is super soft and the fit is perfect. Highly recommended!', 'product_idx' => 0],
            ['rating' => 4, 'title' => 'Great value', 'comment' => 'Good quality t-shirt at affordable price.', 'product_idx' => 0],
            ['rating' => 5, 'title' => 'Best purchase ever', 'comment' => 'The oversized fit is exactly what I wanted!', 'product_idx' => 1],
            ['rating' => 5, 'title' => 'Worth every rupee', 'comment' => 'Premium quality fabric. Color is exactly as shown.', 'product_idx' => 2],
            ['rating' => 4, 'title' => 'Awesome design', 'comment' => 'The graphic is amazing. Everyone asks about it.', 'product_idx' => 3],
            ['rating' => 4, 'title' => 'Nice polo', 'comment' => 'Great fit and fabric quality.', 'product_idx' => 5],
            ['rating' => 5, 'title' => 'Best value pack', 'comment' => 'Three high-quality tees at an amazing price.', 'product_idx' => 6],
            ['rating' => 4, 'title' => 'Great cap', 'comment' => 'Structured fit, looks premium.', 'product_idx' => 12],
        ];

        foreach ($reviewData as $r) {
            if (isset($products[$r['product_idx']])) {
                Review::create([
                    'product_id' => $products[$r['product_idx']]['model']->id,
                    'user_id' => $users[array_rand($users)]->id,
                    'rating' => $r['rating'],
                    'title' => $r['title'],
                    'comment' => $r['comment'],
                    'is_verified' => true, 'is_moderated' => true,
                ]);
            }
        }
        $this->command->info('   ✓ Reviews created');

        // =====================
        // 13. ORDERS
        // =====================
        $this->command->info('📋 Seeding orders...');

        $orderStatuses = ['DELIVERED', 'DELIVERED', 'DELIVERED', 'PENDING', 'PROCESSING', 'SHIPPED', 'CANCELLED'];
        $statusTimestamps = [
            'DELIVERED' => now()->subDays(rand(1, 10)),
            'PENDING' => now()->subHours(rand(1, 12)),
            'PROCESSING' => now()->subDays(rand(1, 3)),
            'SHIPPED' => now()->subDays(rand(2, 5)),
            'CANCELLED' => now()->subDays(rand(5, 15)),
        ];

        $createdOrders = [];
        foreach ($orderStatuses as $idx => $status) {
            $customerIdx = $idx % count($users);
            $customer = $users[$customerIdx];

            // Pick 1-3 random products for this order
            $numItems = rand(1, 3);
            $orderProducts = [];
            $usedIndices = [];
            for ($i = 0; $i < $numItems; $i++) {
                $pi = array_rand($products);
                while (in_array($pi, $usedIndices)) $pi = array_rand($products);
                $usedIndices[] = $pi;
                $orderProducts[] = $products[$pi];
            }

            // Calculate subtotal
            $subtotal = 0;
            $itemsData = [];
            foreach ($orderProducts as $op) {
                $qty = rand(1, 3);
                $prodModel = $op['model'];
                $price = $prodModel->price;
                $itemsData[] = ['product' => $prodModel, 'qty' => $qty, 'price' => $price];
                $subtotal += $price * $qty;
            }

            $tax = round($subtotal * 0.18, 2);           // 18% GST
            $shippingCost = $subtotal >= 499 ? 0 : 50;    // Free above ₹499
            $discount = $subtotal > 1000 ? round($subtotal * 0.1, 2) : 0;
            $total = $subtotal + $tax + $shippingCost - $discount;
            $orderNumber = 'ORD-' . now()->format('Ymd') . '-' . str_pad($idx + 1, 4, '0', STR_PAD_LEFT);

            // Get customer's address
            $address = Address::where('user_id', $customer->id)->first();
            $addressId = $address ? $address->id : Address::create([
                'user_id' => $customer->id, 'type' => 'HOME',
                'first_name' => $customer->first_name, 'last_name' => $customer->last_name,
                'phone_number' => $customer->phone_number ?? '+91 98765 43210',
                'address_line1' => 'Sample Address', 'city' => 'Bangalore',
                'state' => 'Karnataka', 'zip_code' => '560001', 'country' => 'India', 'is_default' => true,
            ])->id;

            $order = Order::create([
                'order_number' => $orderNumber,
                'user_id' => $customer->id,
                'shipping_address_id' => $addressId,
                'billing_address_id' => $addressId,
                'subtotal' => $subtotal,
                'tax' => $tax,
                'shipping_cost' => $shippingCost,
                'discount' => $discount,
                'total' => $total,
                'status' => $status,
                'notes' => 'Sample order for testing',
                'confirmed_at' => $status === 'PENDING' ? null : $statusTimestamps[$status],
                'processing_at' => in_array($status, ['PROCESSING', 'SHIPPED', 'DELIVERED']) ? $statusTimestamps[$status] : null,
                'shipped_at' => in_array($status, ['SHIPPED', 'DELIVERED']) ? $statusTimestamps[$status] : null,
                'delivered_at' => $status === 'DELIVERED' ? $statusTimestamps[$status] : null,
                'cancelled_at' => $status === 'CANCELLED' ? $statusTimestamps[$status] : null,
                'created_at' => $statusTimestamps[$status],
            ]);

            // Create order items
            foreach ($itemsData as $item) {
                $itemTotal = round($item['price'] * $item['qty'], 2);
                OrderItem::create([
                    'order_id' => $order->id,
                    'user_id' => $customer->id,
                    'product_id' => $item['product']->id,
                    'quantity' => $item['qty'],
                    'price' => $item['price'],
                    'total' => $itemTotal,
                    'created_at' => $statusTimestamps[$status],
                ]);
            }

            // Create payment
            $paymentMethods = ['RAZORPAY', 'COD', 'STRIPE'];
            $paymentStatuses = ['COMPLETED', 'COMPLETED', 'COMPLETED', 'PENDING', 'COMPLETED', 'COMPLETED', 'REFUNDED'];
            Payment::create([
                'order_id' => $order->id,
                'transaction_id' => 'TXN-' . Str::random(16),
                'method' => $paymentMethods[array_rand($paymentMethods)],
                'amount' => $total,
                'currency' => 'INR',
                'status' => $paymentStatuses[$idx] ?? 'COMPLETED',
                'created_at' => $statusTimestamps[$status],
            ]);

            // Create shipping record (not for PENDING or CANCELLED)
            if (!in_array($status, ['PENDING', 'CANCELLED'])) {
                $carriers = ['Delhivery', 'Blue Dart', 'India Post', 'DTDC'];
                Shipping::create([
                    'order_id' => $order->id,
                    'carrier' => $carriers[array_rand($carriers)],
                    'tracking_number' => strtoupper(Str::random(12)),
                    'cost' => $shippingCost,
                    'status' => $status === 'DELIVERED' ? 'DELIVERED' : ($status === 'SHIPPED' ? 'IN_TRANSIT' : 'PROCESSING'),
                    'estimated_delivery' => now()->addDays(3),
                    'actual_delivery' => $status === 'DELIVERED' ? $statusTimestamps[$status] : null,
                    'created_at' => $statusTimestamps[$status],
                ]);
            }

            // Create timeline entries
            $timelineStatuses = ['PENDING', 'CONFIRMED'];
            if (in_array($status, ['PROCESSING', 'SHIPPED', 'DELIVERED'])) $timelineStatuses[] = 'PROCESSING';
            if (in_array($status, ['SHIPPED', 'DELIVERED'])) $timelineStatuses[] = 'SHIPPED';
            if ($status === 'DELIVERED') $timelineStatuses[] = 'DELIVERED';
            if ($status === 'CANCELLED') $timelineStatuses = ['PENDING', 'CANCELLED'];

            $descriptions = [
                'PENDING' => 'Order placed successfully',
                'CONFIRMED' => 'Order confirmed by store',
                'PROCESSING' => 'Order is being prepared',
                'SHIPPED' => 'Package has been shipped',
                'DELIVERED' => 'Package delivered successfully',
                'CANCELLED' => 'Order was cancelled',
            ];

            foreach ($timelineStatuses as $ts) {
                OrderTimeline::create([
                    'order_id' => $order->id,
                    'status' => $ts,
                    'description' => $descriptions[$ts] ?? $ts,
                    'created_at' => $statusTimestamps[$status],
                ]);
            }

            $createdOrders[] = $order;
        }

        $this->command->info('   ✓ ' . count($createdOrders) . ' orders with items, payments & timelines created');

        // =====================
        // 14. SHIPPING ZONES & RATES
        // =====================
        $this->command->info('🚚 Seeding shipping zones...');
        $zone1 = ShippingZone::create(['name' => 'Metro Cities', 'countries' => json_encode(['India']), 'states' => json_encode(['Delhi', 'Maharashtra', 'Karnataka', 'Tamil Nadu', 'Telangana'])]);
        $zone2 = ShippingZone::create(['name' => 'Tier 2 Cities', 'countries' => json_encode(['India']), 'states' => json_encode(['Gujarat', 'Rajasthan', 'West Bengal', 'Madhya Pradesh', 'Kerala'])]);

        ShippingRate::insert([
            ['id' => Str::uuid(), 'zone_id' => $zone1->id, 'min_weight' => 0, 'max_weight' => 500, 'cost' => 49, 'free_shipping_above' => 499, 'created_at' => now(), 'updated_at' => now()],
            ['id' => Str::uuid(), 'zone_id' => $zone1->id, 'min_weight' => 501, 'max_weight' => 1000, 'cost' => 79, 'free_shipping_above' => 799, 'created_at' => now(), 'updated_at' => now()],
            ['id' => Str::uuid(), 'zone_id' => $zone2->id, 'min_weight' => 0, 'max_weight' => 500, 'cost' => 79, 'free_shipping_above' => 699, 'created_at' => now(), 'updated_at' => now()],
            ['id' => Str::uuid(), 'zone_id' => $zone2->id, 'min_weight' => 501, 'max_weight' => 1000, 'cost' => 99, 'free_shipping_above' => 999, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Notifications
        foreach ($users as $user) {
            DB::table('notifications')->insert([
                ['id' => Str::uuid(), 'user_id' => $user->id, 'type' => 'SYSTEM', 'title' => 'Welcome!', 'message' => 'Welcome to THREVOLT! Start shopping now.', 'is_read' => false, 'created_at' => now(), 'updated_at' => now()],
                ['id' => Str::uuid(), 'user_id' => $user->id, 'type' => 'PROMOTION', 'title' => 'Summer Sale', 'message' => 'Get 30% off on all items! Use code SAVE30.', 'is_read' => true, 'created_at' => now(), 'updated_at' => now()],
            ]);
        }
        $this->command->info('   ✓ Shipping zones, rates & notifications created');

        // =====================
        // 15. SEO DATA
        // =====================
        $this->command->info('🔍 Seeding SEO data...');
        Seo::create([
            'entity_type' => 'HOME', 'entity_id' => 'home',
            'meta_title' => "THREVOLT - India's Boldest T-Shirt Brand",
            'meta_description' => 'Premium quality t-shirts with bold designs. Free shipping on orders above ₹499.',
            'meta_keywords' => 't-shirts, oversize tees, graphic tees, streetwear',
        ]);
        Sitemap::create(['url' => 'https://threvolt.com/sitemap.xml', 'last_modified' => now()]);
        RobotsTxt::create(['content' => "User-agent: *\nAllow: /\nSitemap: https://threvolt.com/sitemap.xml"]);
        $this->command->info('   ✓ SEO data created');

        // =====================
        // 16. SUBSCRIBERS
        // =====================
        Subscriber::insert([
            ['id' => Str::uuid(), 'email' => 'rahul.sharma@email.com', 'name' => 'Rahul Sharma', 'source' => 'SIGNUP', 'status' => 'SUBSCRIBED', 'created_at' => now(), 'updated_at' => now()],
            ['id' => Str::uuid(), 'email' => 'priya.patel@email.com', 'name' => 'Priya Patel', 'source' => 'SIGNUP', 'status' => 'SUBSCRIBED', 'created_at' => now(), 'updated_at' => now()],
            ['id' => Str::uuid(), 'email' => 'amit.kumar@email.com', 'name' => 'Amit Kumar', 'source' => 'SIGNUP', 'status' => 'SUBSCRIBED', 'created_at' => now(), 'updated_at' => now()],
            ['id' => Str::uuid(), 'email' => 'sneha.gupta@example.com', 'name' => 'Sneha Gupta', 'source' => 'CHECKOUT', 'status' => 'SUBSCRIBED', 'created_at' => now(), 'updated_at' => now()],
        ]);
        $this->command->info('   ✓ Subscribers created');

        // =====================
        // 17. CURATED LOOKS
        // =====================
        $this->command->info('🎨 Seeding curated looks...');

        $curatedLooks = [
            [
                'name' => 'Summer Essentials',
                'slug' => 'summer-essentials',
                'description' => 'Light fabrics and breezy fits for the season ahead.',
                'image_url' => 'https://images.unsplash.com/photo-1523381210434-271e8be1f52b?q=80&w=800',
                'display_order' => 0,
                'is_active' => true,
            ],
            [
                'name' => 'Streetwear Icons',
                'slug' => 'streetwear-icons',
                'description' => 'Bold graphics and oversized silhouettes that define urban style.',
                'image_url' => 'https://images.unsplash.com/photo-1572495641004-28421ae7c9d2?q=80&w=800',
                'display_order' => 1,
                'is_active' => true,
            ],
            [
                'name' => 'Minimal Luxe',
                'slug' => 'minimal-luxe',
                'description' => 'Clean lines. Subtle details. Understated elegance for everyday.',
                'image_url' => 'https://images.unsplash.com/photo-1552374196-1ab2a1c593e8?q=80&w=800',
                'display_order' => 2,
                'is_active' => true,
            ],
            [
                'name' => 'New Arrivals',
                'slug' => 'new-arrivals',
                'description' => 'The freshest drops — be the first to wear them.',
                'image_url' => 'https://images.unsplash.com/photo-1529374255404-311a2a4f1fd9?q=80&w=800',
                'display_order' => 3,
                'is_active' => true,
            ],
        ];

        foreach ($curatedLooks as $look) {
            CuratedLook::create($look);
        }
        $this->command->info('   ✓ ' . count($curatedLooks) . ' curated looks created');

        // =====================
        // 18. REELS
        // =====================
        $this->command->info('🎬 Seeding reels...');

        $reelData = [
            [
                'title' => 'Summer Collection 2024',
                'description' => 'Light fabrics and breezy fits for the season ahead. Shop the latest drops.',
                'video_url' => '',
                'image_url' => 'https://images.unsplash.com/photo-1523381210434-271e8be1f52b?q=80&w=600',
                'link_url' => '/products?category=oversized-collection',
                'display_order' => 0,
                'is_active' => true,
            ],
            [
                'title' => 'Streetwear Icons',
                'description' => 'Bold graphics and oversized silhouettes that define urban style.',
                'video_url' => 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ForBiggerBlazes.mp4',
                'image_url' => 'https://images.unsplash.com/photo-1572495641004-28421ae7c9d2?q=80&w=600',
                'link_url' => '/products?category=legendary-series',
                'display_order' => 1,
                'is_active' => true,
            ],
            [
                'title' => 'Minimal Luxe Edit',
                'description' => 'Clean lines. Subtle details. Understated elegance for everyday.',
                'video_url' => '',
                'image_url' => 'https://images.unsplash.com/photo-1552374196-1ab2a1c593e8?q=80&w=600',
                'link_url' => '/products',
                'display_order' => 2,
                'is_active' => true,
            ],
            [
                'title' => 'New Drops Available Now',
                'description' => 'The freshest styles just landed — be the first to wear them.',
                'video_url' => '',
                'image_url' => 'https://images.unsplash.com/photo-1529374255404-311a2a4f1fd9?q=80&w=600',
                'link_url' => '/products/section/new-arrivals',
                'display_order' => 3,
                'is_active' => true,
            ],
            [
                'title' => 'Oversized Tees — The Edit',
                'description' => 'Drop shoulder, boxy fit, premium cotton. The ultimate comfort wear.',
                'video_url' => 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ForBiggerEscapes.mp4',
                'image_url' => 'https://images.unsplash.com/photo-1583743814966-8936f5b7be1a?q=80&w=600',
                'link_url' => '/products?category=oversized-collection',
                'display_order' => 4,
                'is_active' => true,
            ],
        ];

        foreach ($reelData as $reel) {
            Reel::create($reel);
        }
        $this->command->info('   ✓ ' . count($reelData) . ' reels created');

        // =====================
        // Summary
        // =====================
        $this->command->info("\n🎉 Database seeding completed successfully!\n");
        $this->command->info("📊 Summary:");
        $this->command->info("- Admin: admin@threvolt.com / Admin@123");
        $this->command->info("- Customer: customer@threvolt.com / Demo@123");
        $this->command->info("- Products: ".count($productData));
        $this->command->info("- Orders: ".count($createdOrders));
        $this->command->info("- Coupons: ".count($couponList));
        $this->command->info("- Customers: ".count($users));
        $this->command->info("- Settings: ".count($settings));
        $this->command->info("- Curated Looks: ".count($curatedLooks));
        $this->command->info("- Reels: ".count($reelData));

        // Pre-warm all dashboard caches so first visit is fast (no cold cache)
        $this->command->info('♨️  Warming dashboard cache...');
        try {
            Artisan::call('dashboard:warm-cache', ['--force' => true]);
            $this->command->info('   ✓ Dashboard cache warmed');
        } catch (\Throwable $e) {
            $this->command->warn('   ⚠ Cache warm skipped: '.$e->getMessage());
        }
    }
}
