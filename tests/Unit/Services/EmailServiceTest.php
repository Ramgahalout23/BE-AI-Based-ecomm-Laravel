<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\EmailService;

class EmailServiceTest extends TestCase
{
    /**
     * Invoke the private buildOrderConfirmationHtml builder with sample data.
     */
    private function buildOrderHtml(array $data): string
    {
        $service = new EmailService();
        $method = new \ReflectionMethod(EmailService::class, 'buildOrderConfirmationHtml');
        $method->setAccessible(true);
        return $method->invoke($service, $data);
    }

    private function sampleData(array $overrides = []): array
    {
        return array_merge([
            'orderNumber' => 'ORD-1001',
            'customerName' => 'John Doe',
            'items' => [
                ['name' => 'Classic White T-Shirt', 'quantity' => 2, 'price' => 29.99, 'total' => 59.98],
            ],
            'subtotal' => 59.98,
            'shippingCost' => 5.00,
            'tax' => 11.70,
            'discount' => 0,
            'total' => 76.68,
            'shippingAddress' => '123 Main St, New York, NY 10001',
            'paymentMethod' => 'Credit Card',
        ], $overrides);
    }

    /** @test */
    public function order_confirmation_email_renders_tax_as_separate_line_when_tax_applies()
    {
        $html = $this->buildOrderHtml($this->sampleData(['tax' => 11.70]));

        $this->assertStringContainsString('>Tax</td>', $html);
        $this->assertStringContainsString('Rs. 11.70', $html);
        // Inclusive note must NOT appear when tax is itemized
        $this->assertStringNotContainsString('Inclusive of all taxes', $html);
    }

    /** @test */
    public function order_confirmation_email_hides_tax_line_and_shows_inclusive_note_when_tax_is_zero()
    {
        // Inclusive tax mode → stored tax is 0 → no separate tax line
        $html = $this->buildOrderHtml($this->sampleData(['tax' => 0]));

        $this->assertStringNotContainsString('>Tax</td>', $html);
        $this->assertStringContainsString('Inclusive of all taxes', $html);
    }

    /** @test */
    public function order_confirmation_email_uses_store_currency_symbol()
    {
        $html = $this->buildOrderHtml($this->sampleData(['currency' => 'USD']));

        $this->assertStringContainsString('$ 59.98', $html);
        $this->assertStringContainsString('$ 11.70', $html);
        // Default (INR) when no currency passed
        $default = $this->buildOrderHtml($this->sampleData());
        $this->assertStringContainsString('Rs. 59.98', $default);
    }

    /** @test */
    public function order_confirmation_email_renders_discount_row_only_when_discount_applies()
    {
        $withDiscount = $this->buildOrderHtml($this->sampleData(['discount' => 10.00]));
        $this->assertStringContainsString('Discount</td>', $withDiscount);
        $this->assertStringContainsString('-Rs. 10.00', $withDiscount);

        $withoutDiscount = $this->buildOrderHtml($this->sampleData(['discount' => 0]));
        $this->assertStringNotContainsString('Discount</td>', $withoutDiscount);
    }
}
