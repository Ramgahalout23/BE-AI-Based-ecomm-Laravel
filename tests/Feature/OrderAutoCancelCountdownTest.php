<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\TestCase;

class OrderAutoCancelCountdownTest extends TestCase
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

    /** @test */
    public function pending_order_exposes_auto_cancel_window_matching_config()
    {
        config(['orders.auto_cancel_minutes' => 45]);

        $order = $this->createOrder();
        $data = app(OrderService::class)->getOrder($order->id);

        $this->assertSame(45, $data['autoCancelMinutes']);

        $expected = $order->created_at
            ->addMinutes(45)
            ->toIso8601String();
        $this->assertSame($expected, $data['autoCancelAt']);
        // Window is in the future — the countdown has time left
        $this->assertTrue(strtotime($data['autoCancelAt']) > time());
    }

    /** @test */
    public function auto_cancel_window_falls_back_to_config_when_setting_absent()
    {
        // The migration seeds autoCancelUnpaidMinutes=45 — remove it so this test
        // covers the pure config-fallback path (no DB setting row).
        Setting::whereIn('key', ['autoCancelUnpaidEnabled', 'autoCancelUnpaidMinutes'])->delete();
        config(['orders.auto_cancel_minutes' => 30]);

        $order = $this->createOrder();
        $data = app(OrderService::class)->getOrder($order->id);

        $this->assertSame(30, $data['autoCancelMinutes']);
        $this->assertSame(
            $order->created_at->addMinutes(30)->toIso8601String(),
            $data['autoCancelAt']
        );
    }

    /** @test */
    public function non_pending_orders_have_no_auto_cancel_window()
    {
        $order = $this->createOrder(['status' => 'CONFIRMED']);
        $data = app(OrderService::class)->getOrder($order->id);

        $this->assertNull($data['autoCancelMinutes']);
        $this->assertNull($data['autoCancelAt']);
    }

    /** @test */
    public function cancelled_unpaid_orders_show_expired_window_not_future()
    {
        config(['orders.auto_cancel_minutes' => 45]);

        // An abandoned order that's already past the window (simulates what the
        // scheduler will cancel) — the countdown must show 00:00/expired.
        $order = $this->createOrder();
        $order->created_at = now()->subMinutes(60);
        $order->save();

        $data = app(OrderService::class)->getOrder($order->id);

        $this->assertTrue(strtotime($data['autoCancelAt']) < time());
        $this->assertSame(45, $data['autoCancelMinutes']);
    }

    /** @test */
    public function admin_setting_overrides_config_for_the_window()
    {
        config(['orders.auto_cancel_minutes' => 45]);
        Setting::updateOrCreate(['module' => 'SITE', 'key' => 'autoCancelUnpaidMinutes'], ['value' => '60']);

        $order = $this->createOrder();
        $data = app(OrderService::class)->getOrder($order->id);

        $this->assertSame(60, $data['autoCancelMinutes']);
        $this->assertSame(
            $order->created_at->addMinutes(60)->toIso8601String(),
            $data['autoCancelAt']
        );
    }

    /** @test */
    public function disabling_the_feature_hides_the_deadline()
    {
        Setting::updateOrCreate(['module' => 'SITE', 'key' => 'autoCancelUnpaidEnabled'], ['value' => 'false']);

        $order = $this->createOrder();
        $data = app(OrderService::class)->getOrder($order->id);

        $this->assertNull($data['autoCancelMinutes']);
        $this->assertNull($data['autoCancelAt']);
    }
}
