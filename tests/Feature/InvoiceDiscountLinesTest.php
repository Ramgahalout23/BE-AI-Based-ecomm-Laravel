<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class InvoiceDiscountLinesTest extends TestCase
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
            'status' => 'CONFIRMED',
        ], $overrides));
    }

    private function renderInvoice(Order $order): string
    {
        return view('invoices.standard', [
            'order' => $order,
            'company' => ['name' => 'THREVOLT', 'tagline' => 'Premium', 'email' => 'support@threvolt.com', 'logo' => ''],
            'currency' => 'INR',
            'currencySymbol' => 'Rs.',
            'invoiceNumber' => 'INV-' . $order->order_number,
            'generatedAt' => now()->format('Y-m-d'),
        ])->render();
    }

    /** @test */
    public function invoice_renders_bundle_and_flash_sale_discounts_as_separate_lines()
    {
        $order = $this->createOrder([
            'discount' => 80,
            'bundle_discount' => 50,
            'flash_sale_discount' => 30,
            'total' => 970,
        ]);

        $html = $this->renderInvoice($order);

        $this->assertStringContainsString('Bundle Discount</td>', $html);
        $this->assertStringContainsString('-Rs. 50.00', $html);
        $this->assertStringContainsString('Flash Sale Discount</td>', $html);
        $this->assertStringContainsString('-Rs. 30.00', $html);
        // No remainder discount line when bundle + flash sale cover the full discount
        $this->assertStringNotContainsString('<td>Discount</td>', $html);
    }

    /** @test */
    public function invoice_shows_remainder_as_discount_when_split_does_not_cover_full_discount()
    {
        // e.g. coupon amount on top of bundle + flash sale
        $order = $this->createOrder([
            'discount' => 120,
            'bundle_discount' => 50,
            'flash_sale_discount' => 30,
            'total' => 930,
        ]);

        $html = $this->renderInvoice($order);

        $this->assertStringContainsString('Bundle Discount</td>', $html);
        $this->assertStringContainsString('Flash Sale Discount</td>', $html);
        $this->assertStringContainsString('<td>Discount</td>', $html);
        $this->assertStringContainsString('-Rs. 40.00', $html); // 120 - 50 - 30
    }

    /** @test */
    public function legacy_order_with_combined_discount_renders_single_discount_line()
    {
        // Historical orders predate the split columns — combined discount still shows as one line
        $order = $this->createOrder(['discount' => 60, 'total' => 990]);

        $html = $this->renderInvoice($order);

        $this->assertStringNotContainsString('Bundle Discount</td>', $html);
        $this->assertStringNotContainsString('Flash Sale Discount</td>', $html);
        $this->assertStringContainsString('<td>Discount</td>', $html);
        $this->assertStringContainsString('-Rs. 60.00', $html);
    }

    /** @test */
    public function invoice_omits_discount_lines_when_there_is_no_discount()
    {
        $order = $this->createOrder(['total' => 1050]);

        $html = $this->renderInvoice($order);

        $this->assertStringNotContainsString('Bundle Discount</td>', $html);
        $this->assertStringNotContainsString('Flash Sale Discount</td>', $html);
        $this->assertStringNotContainsString('<td>Discount</td>', $html);
    }
}
