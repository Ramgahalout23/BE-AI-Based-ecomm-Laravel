<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ClaimGuestOrdersTest extends TestCase
{
    use RefreshDatabase;

    private function createUser(string $email, array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'email' => $email,
            'password' => bcrypt('Password1!'),
        ], $overrides));
    }

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
            'quantity' => 10,
            'price' => 349,
        ]);
    }

    private function createOrderFor(User $owner, string $addressEmail): Order
    {
        $address = Address::create([
            'user_id' => $owner->id,
            'type' => 'HOME',
            'first_name' => 'Test',
            'last_name' => 'User',
            'phone_number' => '1234567890',
            'email' => $addressEmail,
            'address_line1' => '1 Main St',
            'city' => 'City',
            'state' => 'State',
            'zip_code' => '12345',
            'country' => 'India',
        ]);

        $order = Order::create([
            'order_number' => 'ORD-' . strtoupper(Str::random(6)),
            'user_id' => $owner->id,
            'shipping_address_id' => $address->id,
            'subtotal' => 698,
            'tax' => 0,
            'shipping_cost' => 0,
            'discount' => 0,
            'bundle_discount' => 0,
            'flash_sale_discount' => 0,
            'total' => 698,
            'status' => 'CONFIRMED',
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'user_id' => $owner->id,
            'product_id' => $this->createProduct()->id,
            'quantity' => 2,
            'price' => 349,
            'total' => 698,
        ]);

        return $order;
    }

    /** @test */
    public function claims_guest_orders_whose_shipping_address_email_matches_on_login()
    {
        // Guest checked out without an account email → placeholder guest account,
        // but the shipping address carried the real email.
        $guest = $this->createUser('guest_abc123@checkout.local');
        $order = $this->createOrderFor($guest, 'customer@example.com');

        $me = $this->createUser('customer@example.com');

        $this->postJson('/api/v1/auth/login', [
            'email' => 'customer@example.com',
            'password' => 'Password1!',
        ])->assertOk()->assertJson(['success' => true]);

        $this->assertEquals($me->id, $order->fresh()->user_id);
        $this->assertEquals($me->id, OrderItem::where('order_id', $order->id)->value('user_id'));
        $this->assertEquals($me->id, Address::find($order->shipping_address_id)->user_id);
    }

    /** @test */
    public function leaves_orders_with_unrelated_emails_alone()
    {
        $guest = $this->createUser('guest_def456@checkout.local');
        $order = $this->createOrderFor($guest, 'someone-else@example.com');

        $me = $this->createUser('customer@example.com');

        $claimed = app(OrderService::class)->claimGuestOrders($me);

        $this->assertEquals(0, $claimed);
        $this->assertEquals($guest->id, $order->fresh()->user_id);
    }

    /** @test */
    public function does_not_steal_orders_already_owned_by_the_user()
    {
        $me = $this->createUser('customer@example.com');
        $order = $this->createOrderFor($me, 'customer@example.com');

        $claimed = app(OrderService::class)->claimGuestOrders($me);

        $this->assertEquals(0, $claimed);
        $this->assertEquals($me->id, $order->fresh()->user_id);
    }

    /** @test */
    public function claims_on_registration_when_a_placeholder_guest_used_the_same_shipping_email()
    {
        $guest = $this->createUser('guest_xyz789@checkout.local');
        $order = $this->createOrderFor($guest, 'new-user@example.com');

        $this->postJson('/api/v1/auth/register', [
            'first_name' => 'New',
            'last_name' => 'User',
            'email' => 'new-user@example.com',
            'password' => 'Password1!',
            'password_confirmation' => 'Password1!',
        ])->assertCreated();

        $newUser = User::where('email', 'new-user@example.com')->firstOrFail();
        $this->assertEquals($newUser->id, $order->fresh()->user_id);
        $this->assertNotEquals($guest->id, $order->fresh()->user_id);
    }
}
