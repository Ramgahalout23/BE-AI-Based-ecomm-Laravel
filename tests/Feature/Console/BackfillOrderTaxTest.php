<?php

namespace Tests\Feature\Console;

use App\Models\Address;
use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class BackfillOrderTaxTest extends TestCase
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
            'discount' => 100,
            'total' => 950,
            'status' => 'CONFIRMED',
        ], $overrides));
    }

    private function seedSettings(string $calculation = 'exclusive', string $rate = '18.0'): void
    {
        Setting::create(['module' => 'TAX', 'key' => 'taxCalculation', 'value' => $calculation]);
        Setting::create(['module' => 'TAX', 'key' => 'taxRate', 'value' => $rate]);
    }

    public function test_backfills_tax_and_recomputes_total_in_exclusive_mode(): void
    {
        $this->seedSettings();
        $order = $this->createOrder();

        $this->artisan('orders:backfill-tax')
            ->expectsOutputToContain('1 order(s) updated')
            ->assertExitCode(0);

        $order->refresh();
        $this->assertEquals(180.00, (float) $order->tax);    // 1000 * 18%
        $this->assertEquals(1130.00, (float) $order->total);  // 1000 + 180 + 50 - 100
    }

    public function test_skips_in_inclusive_mode(): void
    {
        $this->seedSettings('inclusive');
        $order = $this->createOrder();

        $this->artisan('orders:backfill-tax')
            ->expectsOutputToContain('Skipping')
            ->assertExitCode(0);

        $order->refresh();
        $this->assertEquals(0.00, (float) $order->tax);
        $this->assertEquals(950.00, (float) $order->total);
    }

    public function test_dry_run_does_not_modify_orders(): void
    {
        $this->seedSettings();
        $order = $this->createOrder();

        $this->artisan('orders:backfill-tax --dry-run')
            ->expectsOutputToContain('DRY RUN')
            ->assertExitCode(0);

        $order->refresh();
        $this->assertEquals(0.00, (float) $order->tax);
        $this->assertEquals(950.00, (float) $order->total);
    }

    public function test_rate_override_is_honored(): void
    {
        $this->seedSettings();
        $order = $this->createOrder();

        $this->artisan('orders:backfill-tax --rate=7.5')
            ->assertExitCode(0);

        $order->refresh();
        $this->assertEquals(75.00, (float) $order->tax);    // 1000 * 7.5%
        $this->assertEquals(1025.00, (float) $order->total); // 1000 + 75 + 50 - 100
    }

    public function test_leaves_orders_with_existing_tax_untouched(): void
    {
        $this->seedSettings();
        $order = $this->createOrder(['tax' => 99, 'total' => 1049]);

        $this->artisan('orders:backfill-tax')->assertExitCode(0);

        $order->refresh();
        $this->assertEquals(99.00, (float) $order->tax);
        $this->assertEquals(1049.00, (float) $order->total);
    }

    public function test_invalidates_cached_order_after_update(): void
    {
        $this->seedSettings();
        $order = $this->createOrder();
        \Illuminate\Support\Facades\Cache::put('order_' . $order->id, ['stale' => true], 60);

        $this->artisan('orders:backfill-tax')->assertExitCode(0);

        $this->assertNull(\Illuminate\Support\Facades\Cache::get('order_' . $order->id));
    }
}
