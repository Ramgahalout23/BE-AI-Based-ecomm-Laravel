<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\CheckoutService;
use App\Repositories\CartRepository;
use App\Repositories\OrderRepository;
use App\Repositories\CouponRepository;
use App\Services\SettingsService;
use App\Services\FlashSaleService;
use Mockery;

class CheckoutServiceTest extends TestCase
{
    protected CheckoutService $checkoutService;
    protected SettingsService|Mockery\MockInterface $settingsService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->settingsService = Mockery::mock(SettingsService::class);
        $this->checkoutService = new CheckoutService(
            Mockery::mock(CartRepository::class),
            Mockery::mock(OrderRepository::class),
            Mockery::mock(CouponRepository::class),
            $this->settingsService,
            Mockery::mock(FlashSaleService::class),
        );
    }

    protected function tearDown(): void
    {
        // Verify but don't close — preserves alias mocks across tests
        if ($container = Mockery::getContainer()) {
            $container->mockery_verify();
        }
        parent::tearDown();
    }

    /** @test */
    public function calculateTax_returns_zero_for_inclusive_mode()
    {
        // 'inclusive' → prices already include tax → no extra tax charged
        $this->settingsService->shouldReceive('get')->with('taxCalculation', 'inclusive')->andReturn('inclusive');
        $this->settingsService->shouldReceive('get')->with('taxRate', '18.0')->andReturn('18.0');

        $this->assertSame(0.0, $this->checkoutService->calculateTax(1000.0));
    }

    /** @test */
    public function calculateTax_returns_zero_when_no_setting_configured()
    {
        // Missing settings → default 'inclusive' → no tax
        $this->settingsService->shouldReceive('get')->with('taxCalculation', 'inclusive')->andReturn(null);
        $this->settingsService->shouldReceive('get')->with('taxRate', '18.0')->andReturn('18.0');

        $this->assertSame(0.0, $this->checkoutService->calculateTax(1000.0));
    }

    /** @test */
    public function calculateTax_applies_rate_for_exclusive_mode()
    {
        // 'exclusive' → 18% added on top of the subtotal
        $this->settingsService->shouldReceive('get')->with('taxCalculation', 'inclusive')->andReturn('exclusive');
        $this->settingsService->shouldReceive('get')->with('taxRate', '18.0')->andReturn('18.0');

        $this->assertSame(180.0, $this->checkoutService->calculateTax(1000.0));
        $this->assertSame(27.0, $this->checkoutService->calculateTax(150.0));
    }

    /** @test */
    public function calculateTax_rounds_to_two_decimals()
    {
        $this->settingsService->shouldReceive('get')->with('taxCalculation', 'inclusive')->andReturn('exclusive');
        $this->settingsService->shouldReceive('get')->with('taxRate', '18.0')->andReturn('7.5');

        $this->assertSame(75.0, $this->checkoutService->calculateTax(1000.0));
        $this->assertSame(12.34, $this->checkoutService->calculateTax(164.53));
    }

    /** @test */
    public function calculateBundleDiscount_returns_zero_when_offer_disabled()
    {
        // Seeded inactive — admin must enable it before the discount applies
        $this->settingsService->shouldReceive('get')->with('salesEnabled', 'true')->andReturn('true');
        $this->settingsService->shouldReceive('get')->with('bundleOfferEnabled', 'false')->andReturn('false');

        $items = [['quantity' => 2, 'price' => 100.0]];
        $this->assertSame(0.0, $this->checkoutService->calculateBundleDiscount($items));
    }

    /** @test */
    public function calculateBundleDiscount_returns_zero_when_sales_globally_disabled()
    {
        // Global sales toggle off → bundle offer must not apply either
        $this->settingsService->shouldReceive('get')->with('salesEnabled', 'true')->andReturn('false');

        $items = [['quantity' => 2, 'price' => 100.0]];
        $this->assertSame(0.0, $this->checkoutService->calculateBundleDiscount($items));
    }

    /** @test */
    public function calculateBundleDiscount_applies_default_tiers_when_enabled()
    {
        $this->settingsService->shouldReceive('get')->with('salesEnabled', 'true')->andReturn('true');
        $this->settingsService->shouldReceive('get')->with('bundleOfferEnabled', 'false')->andReturn('true');
        $this->settingsService->shouldReceive('get')->with('bundleOfferStartDate', '')->andReturn('');
        $this->settingsService->shouldReceive('get')->with('bundleOfferEndDate', '')->andReturn('');
        $this->settingsService->shouldReceive('get')->with('bundleTiers', '')->andReturn('');

        // total qty 5 → 15% (highest default tier) of (200 + 600) = 120
        $items = [
            ['quantity' => 2, 'price' => 100.0],
            ['quantity' => 3, 'price' => 200.0],
        ];
        $this->assertSame(120.0, $this->checkoutService->calculateBundleDiscount($items));
    }

    /** @test */
    public function calculateBundleDiscount_uses_configured_tiers()
    {
        $this->settingsService->shouldReceive('get')->with('salesEnabled', 'true')->andReturn('true');
        $this->settingsService->shouldReceive('get')->with('bundleOfferEnabled', 'false')->andReturn('true');
        $this->settingsService->shouldReceive('get')->with('bundleOfferStartDate', '')->andReturn('');
        $this->settingsService->shouldReceive('get')->with('bundleOfferEndDate', '')->andReturn('');
        $customTiers = json_encode([
            ['minQty' => 3, 'discount' => 8],
            ['minQty' => 5, 'discount' => 20],
        ]);
        $this->settingsService->shouldReceive('get')->with('bundleTiers', '')->andReturn($customTiers);

        // total qty 9 → 20% of (300 + 600) = 180
        $items = [
            ['quantity' => 3, 'price' => 100.0],
            ['quantity' => 6, 'price' => 100.0],
        ];
        $this->assertSame(180.0, $this->checkoutService->calculateBundleDiscount($items));
    }

    /** @test */
    public function calculateBundleDiscount_ignores_lines_below_minimum_tier()
    {
        $this->settingsService->shouldReceive('get')->with('salesEnabled', 'true')->andReturn('true');
        $this->settingsService->shouldReceive('get')->with('bundleOfferEnabled', 'false')->andReturn('true');
        $this->settingsService->shouldReceive('get')->with('bundleOfferStartDate', '')->andReturn('');
        $this->settingsService->shouldReceive('get')->with('bundleOfferEndDate', '')->andReturn('');
        $this->settingsService->shouldReceive('get')->with('bundleTiers', '')->andReturn('');

        // qty 1 has no tier → no discount
        $items = [['quantity' => 1, 'price' => 100.0]];
        $this->assertSame(0.0, $this->checkoutService->calculateBundleDiscount($items));
    }

    /** @test */
    public function calculateBundleDiscount_honors_per_tier_max_quantity_cap()
    {
        $this->settingsService->shouldReceive('get')->with('salesEnabled', 'true')->andReturn('true');
        $this->settingsService->shouldReceive('get')->with('bundleOfferEnabled', 'false')->andReturn('true');
        $this->settingsService->shouldReceive('get')->with('bundleOfferStartDate', '')->andReturn('');
        $this->settingsService->shouldReceive('get')->with('bundleOfferEndDate', '')->andReturn('');
        $tiers = json_encode([
            ['minQty' => 2, 'discount' => 5, 'maxQty' => 3],
            ['minQty' => 4, 'discount' => 10, 'maxQty' => 6],
        ]);
        $this->settingsService->shouldReceive('get')->with('bundleTiers', '')->andReturn($tiers);

        // total qty 3 (in the 2–3 window) → 5% of 300 = 15
        // (a third DIFFERENT item pushes the total past the cap window check)
        $items = [
            ['quantity' => 2, 'price' => 100.0],
            ['quantity' => 1, 'price' => 100.0],
        ];
        $this->assertSame(15.0, $this->checkoutService->calculateBundleDiscount($items));
    }

    /** @test */
    public function calculateBundleDiscount_returns_zero_above_highest_cap()
    {
        $this->settingsService->shouldReceive('get')->with('salesEnabled', 'true')->andReturn('true');
        $this->settingsService->shouldReceive('get')->with('bundleOfferEnabled', 'false')->andReturn('true');
        $this->settingsService->shouldReceive('get')->with('bundleOfferStartDate', '')->andReturn('');
        $this->settingsService->shouldReceive('get')->with('bundleOfferEndDate', '')->andReturn('');
        $tiers = json_encode([
            ['minQty' => 2, 'discount' => 5, 'maxQty' => 3],
            ['minQty' => 4, 'discount' => 10, 'maxQty' => 6],
        ]);
        $this->settingsService->shouldReceive('get')->with('bundleTiers', '')->andReturn($tiers);

        $items = [['quantity' => 7, 'price' => 100.0]];
        $this->assertSame(0.0, $this->checkoutService->calculateBundleDiscount($items));
    }

    /** @test */
    public function calculateBundleDiscount_returns_zero_before_start_date()
    {
        $this->settingsService->shouldReceive('get')->with('salesEnabled', 'true')->andReturn('true');
        $this->settingsService->shouldReceive('get')->with('bundleOfferEnabled', 'false')->andReturn('true');
        // Boundaries computed in the mocked store timezone (IST) so the test is
        // deterministic regardless of the app's UTC clock near midnight.
        $this->settingsService->shouldReceive('get')->with('bundleOfferStartDate', '')->andReturn(now('Asia/Kolkata')->addDay()->format('Y-m-d'));
        $this->settingsService->shouldReceive('get')->with('bundleOfferEndDate', '')->andReturn('');
        $this->settingsService->shouldReceive('get')->with('timezone', 'UTC')->andReturn('IST');
        $this->settingsService->shouldReceive('get')->with('bundleTiers', '')->andReturn('');

        // Today is before the configured start date → offer not yet active
        $items = [['quantity' => 2, 'price' => 100.0]];
        $this->assertSame(0.0, $this->checkoutService->calculateBundleDiscount($items));
    }

    /** @test */
    public function calculateBundleDiscount_returns_zero_after_end_date()
    {
        $this->settingsService->shouldReceive('get')->with('salesEnabled', 'true')->andReturn('true');
        $this->settingsService->shouldReceive('get')->with('bundleOfferEnabled', 'false')->andReturn('true');
        $this->settingsService->shouldReceive('get')->with('bundleOfferStartDate', '')->andReturn('');
        $this->settingsService->shouldReceive('get')->with('bundleOfferEndDate', '')->andReturn(now('Asia/Kolkata')->subDay()->format('Y-m-d'));
        $this->settingsService->shouldReceive('get')->with('timezone', 'UTC')->andReturn('IST');
        $this->settingsService->shouldReceive('get')->with('bundleTiers', '')->andReturn('');

        // Today is past the configured end date → offer expired
        $items = [['quantity' => 2, 'price' => 100.0]];
        $this->assertSame(0.0, $this->checkoutService->calculateBundleDiscount($items));
    }

    /** @test */
    public function calculateBundleDiscount_applies_within_date_window()
    {
        $this->settingsService->shouldReceive('get')->with('salesEnabled', 'true')->andReturn('true');
        $this->settingsService->shouldReceive('get')->with('bundleOfferEnabled', 'false')->andReturn('true');
        $this->settingsService->shouldReceive('get')->with('bundleOfferStartDate', '')->andReturn(now('Asia/Kolkata')->subDay()->format('Y-m-d'));
        $this->settingsService->shouldReceive('get')->with('bundleOfferEndDate', '')->andReturn(now('Asia/Kolkata')->addDay()->format('Y-m-d'));
        $this->settingsService->shouldReceive('get')->with('timezone', 'UTC')->andReturn('IST');
        $this->settingsService->shouldReceive('get')->with('bundleTiers', '')->andReturn('');

        // Today is within [start, end] → discount applies
        $items = [['quantity' => 2, 'price' => 100.0]];
        $this->assertSame(10.0, $this->checkoutService->calculateBundleDiscount($items));
    }
}
