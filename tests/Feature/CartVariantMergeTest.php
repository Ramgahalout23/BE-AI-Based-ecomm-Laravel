<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Repositories\CartRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CartVariantMergeTest extends TestCase
{
    use RefreshDatabase;

    private function createProduct(): Product
    {
        $category = Category::create([
            'name' => 'Apparel',
            'slug' => 'apparel-' . Str::lower(Str::random(6)),
        ]);

        return Product::create([
            'name' => 'Test Tee',
            'slug' => 'test-tee-' . Str::lower(Str::random(6)),
            'description' => 'Test product',
            'sku' => 'PROD-' . Str::upper(Str::random(6)),
            'category_id' => $category->id,
            'status' => 'PUBLISHED',
            'quantity' => 100,
            'price' => 349,
        ]);
    }

    /** Re-adding the same product+size+color increments the same line. */
    public function test_add_or_update_increments_same_variant()
    {
        $product = $this->createProduct();
        $repo = new CartRepository();

        $first = $repo->addOrUpdateItem(null, $product->id, 1, 'sess-test', 'L', 'White');
        $second = $repo->addOrUpdateItem(null, $product->id, 2, 'sess-test', 'L', 'White');

        $this->assertSame(1, $first->quantity);
        $this->assertSame(3, $second->quantity); // 1 + 2 = 3 (increment, not overwrite)
    }

    /** Different size or color of the same product becomes a separate line. */
    public function test_add_or_update_keeps_different_variants_separate()
    {
        $product = $this->createProduct();
        $repo = new CartRepository();

        $repo->addOrUpdateItem(null, $product->id, 1, 'sess-test', 'L', 'White');
        $repo->addOrUpdateItem(null, $product->id, 1, 'sess-test', 'M', 'White');
        $repo->addOrUpdateItem(null, $product->id, 1, 'sess-test', 'L', 'Black');

        $lines = $repo->getCartBySession('sess-test');
        $this->assertCount(3, $lines);
    }

    /** Guest cart merges each variant into its own matching user line. */
    public function test_merge_guest_cart_is_variant_aware()
    {
        $user = User::factory()->create();
        $product = $this->createProduct();
        $repo = new CartRepository();

        // Guest cart: L/White qty 2, M/Black qty 4
        $repo->addOrUpdateItem(null, $product->id, 2, 'sess-guest', 'L', 'White');
        $repo->addOrUpdateItem(null, $product->id, 4, 'sess-guest', 'M', 'Black');

        // User cart already has: L/White qty 1, M/Black qty 1
        $repo->addOrUpdateItem($user->id, $product->id, 1, null, 'L', 'White');
        $repo->addOrUpdateItem($user->id, $product->id, 1, null, 'M', 'Black');

        $repo->mergeGuestCart($user->id, 'sess-guest');

        $lines = $repo->getUserCart($user->id);
        $this->assertCount(2, $lines);
        $byKey = [];
        foreach ($lines as $line) {
            $byKey[$line->size . '/' . $line->color] = $line->quantity;
        }
        $this->assertSame(3, $byKey['L/White']);   // 1 + 2
        $this->assertSame(5, $byKey['M/Black']);   // 1 + 4
    }

    /** Merge endpoint forwards size/color so variant lines don't collapse to null. */
    public function test_merge_endpoint_preserves_variants()
    {
        $user = User::factory()->create();
        $product = $this->createProduct();

        // Simulate a guest localStorage cart with a variant line
        $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/cart/merge', [
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 2,
                    'size' => 'L',
                    'color' => 'White',
                ],
            ],
        ]);

        $response->assertStatus(200);

        $repo = new CartRepository();
        $lines = $repo->getUserCart($user->id);
        $this->assertCount(1, $lines);
        $this->assertSame('L', $lines->first()->size);
        $this->assertSame('White', $lines->first()->color);
        $this->assertSame(2, $lines->first()->quantity);
    }
}
