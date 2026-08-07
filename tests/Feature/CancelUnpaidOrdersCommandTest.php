<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class CancelUnpaidOrdersCommandTest extends TestCase
{
    use RefreshDatabase;

    private function createOrder(array $overrides = []): Order
    {
        $user = User::factory()->create();
        $address = Address::create([
            'user_id' => $user->id,
            'type' => 'HOME',
            'first_name' => 'Test',
            'last_name' => 'User',
            'phone_number' => '1234567890',
            'address_line1' => '1 Main St',
            'city' => 'City',
            'state' => 'State',
            'zip_code' => '12345',
            'country' => 'India',
        ]);

        return Order::create(array_merge([
            'order_number' => 'ORD-' . strtoupper(Str::random(6)),
            'user_id' => $user->id,
            'shipping_address_id' => $address->id,
            'subtotal' => 1000,
            'tax' => 0,
            'shipping_cost' => 50,
            'discount' => 0,
            'bundle_discount' => 0,
            'flash_sale_discount' => 0,
            'total' => 1050,
            'status' => 'PENDING',
        ], $overrides));
    }

    private function ageOrder(Order $order, int $minutes): Order
    {
        $order->created_at = now()->subMinutes($minutes);
        $order->save();

        return $order;
    }

    private function attachPayment(Order $order, string $method, string $status): Payment
    {
        return Payment::create([
            'order_id' => $order->id,
            'method' => $method,
            'amount' => 1050,
            'currency' => 'INR',
            'status' => $status,
        ]);
    }

    /** @test */
    public function cancels_unpaid_pending_orders_older_than_cutoff()
    {
        Queue::fake();

        $abandoned = $this->ageOrder($this->createOrder(), 60);

        $this->artisan('orders:cancel-unpaid', ['--minutes' => 45])
            ->expectsOutputToContain('Cancelled: 1')
            ->assertExitCode(0);

        $this->assertEquals('CANCELLED', $abandoned->fresh()->status);
        $this->assertStringContainsString('payment not received', $abandoned->fresh()->notes);
    }

    /** @test */
    public function leaves_recent_pending_cod_and_confirmed_orders_untouched()
    {
        Queue::fake();

        $recent = $this->createOrder(); // created now → within cutoff
        $cod = $this->ageOrder($this->createOrder(), 60);
        $this->attachPayment($cod, 'COD', 'PENDING');
        $confirmed = $this->ageOrder($this->createOrder(['status' => 'CONFIRMED']), 60);
        $paidButPending = $this->ageOrder($this->createOrder(), 60);
        $this->attachPayment($paidButPending, 'RAZORPAY', 'COMPLETED');

        $this->artisan('orders:cancel-unpaid', ['--minutes' => 45])
            ->expectsOutputToContain('No unpaid PENDING orders to cancel.')
            ->assertExitCode(0);

        $this->assertEquals('PENDING', $recent->fresh()->status);
        $this->assertEquals('PENDING', $cod->fresh()->status);
        $this->assertEquals('CONFIRMED', $confirmed->fresh()->status);
        $this->assertEquals('PENDING', $paidButPending->fresh()->status);
    }

    /** @test */
    public function restores_stock_when_cancelling()
    {
        Queue::fake();

        $category = Category::create([
            'name' => 'Apparel',
            'slug' => 'apparel-' . Str::lower(Str::random(6)),
        ]);
        $product = Product::create([
            'name' => 'Test Tee',
            'slug' => 'test-tee-' . Str::lower(Str::random(6)),
            'description' => 'Test product',
            'sku' => 'PROD-' . Str::upper(Str::random(6)),
            'category_id' => $category->id,
            'status' => 'PUBLISHED',
            'quantity' => 10,
            'price' => 699,
        ]);
        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'name' => 'S / Black',
            'sku' => 'SKU-' . Str::upper(Str::random(6)),
            'quantity' => 5,
            'price' => 699,
        ]);

        $order = $this->ageOrder($this->createOrder(['total' => 1398, 'subtotal' => 1398]), 60);
        OrderItem::create([
            'order_id' => $order->id,
            'user_id' => $order->user_id,
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'quantity' => 2,
            'price' => 699,
            'total' => 1398,
        ]);

        $this->artisan('orders:cancel-unpaid', ['--minutes' => 45])
            ->expectsOutputToContain('Cancelled: 1')
            ->assertExitCode(0);

        $this->assertEquals('CANCELLED', $order->fresh()->status);
        // Item has a variant → variant stock is restored (product-level qty is the
        // fallback for variant-less items and stays untouched)
        $this->assertEquals(7, $variant->fresh()->quantity);   // 5 + 2 restored
        $this->assertEquals(10, $product->fresh()->quantity);
    }

    /** @test */
    public function dry_run_does_not_cancel_anything()
    {
        Queue::fake();

        $abandoned = $this->ageOrder($this->createOrder(), 60);

        $this->artisan('orders:cancel-unpaid', ['--minutes' => 45, '--dry-run' => true])
            ->expectsOutputToContain('[DRY-RUN]')
            ->assertExitCode(0);

        $this->assertEquals('PENDING', $abandoned->fresh()->status);
    }

    /** @test */
    public function respects_the_admin_window_setting_when_no_flag_given()
    {
        Queue::fake();

        Setting::updateOrCreate(['module' => 'SITE', 'key' => 'autoCancelUnpaidMinutes'], ['value' => '30']);
        Cache::forget('setting_autoCancelUnpaidMinutes');

        $outside = $this->ageOrder($this->createOrder(), 40);   // older than 30 → cancel
        $inside = $this->ageOrder($this->createOrder(), 20);    // younger than 30 → keep

        $this->artisan('orders:cancel-unpaid')
            ->expectsOutputToContain('Cancelled: 1')
            ->assertExitCode(0);

        $this->assertEquals('CANCELLED', $outside->fresh()->status);
        $this->assertEquals('PENDING', $inside->fresh()->status);
    }

    /** @test */
    public function skips_everything_when_the_feature_is_disabled()
    {
        Queue::fake();

        Setting::updateOrCreate(['module' => 'SITE', 'key' => 'autoCancelUnpaidEnabled'], ['value' => 'false']);
        Cache::forget('setting_autoCancelUnpaidEnabled');

        $abandoned = $this->ageOrder($this->createOrder(), 120);

        $this->artisan('orders:cancel-unpaid')
            ->expectsOutputToContain('disabled')
            ->assertExitCode(0);

        $this->assertEquals('PENDING', $abandoned->fresh()->status);
    }

    /** @test */
    public function explicit_minutes_flag_bypasses_the_toggle_for_manual_runs()
    {
        Queue::fake();

        Setting::updateOrCreate(['module' => 'SITE', 'key' => 'autoCancelUnpaidEnabled'], ['value' => 'false']);
        Cache::forget('setting_autoCancelUnpaidEnabled');

        $abandoned = $this->ageOrder($this->createOrder(), 60);

        $this->artisan('orders:cancel-unpaid', ['--minutes' => 45])
            ->expectsOutputToContain('Cancelled: 1')
            ->assertExitCode(0);

        $this->assertEquals('CANCELLED', $abandoned->fresh()->status);
    }
}
