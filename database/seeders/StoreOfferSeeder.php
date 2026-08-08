<?php

namespace Database\Seeders;

use App\Models\Promotion;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class StoreOfferSeeder extends Seeder
{
    public function run(): void
    {
        $offers = [
            [
                'id'             => Str::uuid(),
                'title'          => 'Smart Deal',
                'description'    => 'Buy 2 items and get 10% off automatically applied at checkout',
                'type'           => 'SEASONAL',
                'discount'       => 10.00,
                'status'         => 'ACTIVE',
                'is_active'      => true,
                'priority'       => 100,
                'offer_badge'    => 'BUY 2',
                'offer_highlight' => 'GET 10% OFF',
                'offer_tagline'  => 'Auto-applied at checkout',
                'offer_theme'    => 'smart-deal',
                'auto_apply'     => true,
                'created_at'     => now(),
                'updated_at'     => now(),
            ],
            [
                'id'             => Str::uuid(),
                'title'          => 'Prepaid Offer',
                'description'    => 'Extra 10% off on prepaid orders, auto-applied at checkout',
                'type'           => 'SEASONAL',
                'discount'       => 10.00,
                'status'         => 'ACTIVE',
                'is_active'      => true,
                'priority'       => 90,
                'offer_badge'    => null,
                'offer_highlight' => 'EXTRA 10% OFF',
                'offer_tagline'  => 'On prepaid orders · Auto-applied at checkout',
                'offer_theme'    => 'prepaid-offer',
                'auto_apply'     => true,
                'created_at'     => now(),
                'updated_at'     => now(),
            ],
            [
                // Seeded PAUSED: a "FREE GIFT" offer has no % discount to apply, and the
                // auto-add-gift-to-cart behaviour it advertised isn't implemented, so it
                // must stay inactive to avoid a misleading card that never delivers.
                // Give it a real discount value (and re-enable) from Admin → Promotions
                // when the free-gift flow exists.
                'id'             => Str::uuid(),
                'title'          => 'Summer Bonus 🎀',
                'description'    => 'Free scrunchies worth ₹150 automatically added to cart',
                'type'           => 'SEASONAL',
                'discount'       => null,
                'status'         => 'PAUSED',
                'is_active'      => false,
                'priority'       => 80,
                'offer_badge'    => 'FREE GIFT',
                'offer_highlight' => 'Scrunchies Worth ₹150',
                'offer_tagline'  => 'AUTO · Added to Cart',
                'offer_theme'    => 'summer-bonus',
                'auto_apply'     => false,
                'created_at'     => now(),
                'updated_at'     => now(),
            ],
        ];

        foreach ($offers as $offer) {
            Promotion::firstOrCreate(
                ['title' => $offer['title']],
                $offer
            );
        }

        $this->command->info('✓ 3 store offers seeded successfully');
    }
}
