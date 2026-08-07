<?php

namespace App\Services;

use App\Repositories\CartRepository;
use App\Repositories\OrderRepository;
use App\Repositories\CouponRepository;
use App\Exceptions\AppError;
use Illuminate\Support\Str;

class CheckoutService
{
    public function __construct(
        protected CartRepository $cartRepository,
        protected OrderRepository $orderRepository,
        protected CouponRepository $couponRepository,
        protected SettingsService $settingsService,
        protected FlashSaleService $flashSaleService
    ) {}

    protected function getTaxRate(): float
    {
        return (float) ($this->settingsService->get('taxRate', '18.0')) / 100;
    }

    protected function getTaxCalculation(): string
    {
        return (string) $this->settingsService->get('taxCalculation', 'inclusive');
    }

    /**
     * Calculate tax for a subtotal, honoring the admin's taxCalculation setting.
     * - 'inclusive' → prices already include tax → no extra tax charged (0).
     * - 'exclusive' → tax is added on top of the subtotal at checkout.
     */
    public function calculateTax(float $subtotal): float
    {
        if ($this->getTaxCalculation() !== 'exclusive') {
            return 0.0;
        }
        return round($subtotal * $this->getTaxRate(), 2);
    }

    protected function getFreeShippingThreshold(): float
    {
        return (float) ($this->settingsService->get('freeShippingThreshold', '100'));
    }

    protected function getStandardShippingCost(): float
    {
        return (float) ($this->settingsService->get('shippingFlatRate', '10'));
    }

    public function getSummary(string $userId): array
    {
        $items = $this->cartRepository->getUserCart($userId);
        $subtotal = $items->sum(fn($item) => ($item->product?->price ?? 0) * $item->quantity);
        $tax = $this->calculateTax($subtotal);
        $freeShippingThreshold = $this->getFreeShippingThreshold();
        $standardShippingCost = $this->getStandardShippingCost();
        $shipping = $subtotal >= $freeShippingThreshold ? 0 : $standardShippingCost;

        $itemsArray = $items->load('product.images')->toArray();

        // Map flat imageUrl onto each item for consistency with order endpoints
        foreach ($itemsArray as &$item) {
            $item['imageUrl'] = $item['product']['images'][0]['url'] ?? $item['image_url'] ?? null;
        }
        unset($item);

        // Build plain items array for flash sale matching + bundle discount
        $plainItems = $itemsArray;
        foreach ($plainItems as &$plainItem) {
            $plainItem['product_id'] = $plainItem['product']['id'] ?? $plainItem['product_id'] ?? null;
            $plainItem['category_id'] = $plainItem['product']['category_id'] ?? null;
            $plainItem['price'] = $plainItem['product']['price'] ?? $plainItem['price'] ?? 0;
        }
        unset($plainItem);

        $flashSaleDiscounts = $this->flashSaleService->getApplicableDiscounts($plainItems);

        // Buy More, Save More — order-wide volume discount based on total cart quantity
        $bundleDiscount = $this->calculateBundleDiscount($plainItems);

        return [
            'items' => $itemsArray,
            'subtotal' => $subtotal,
            'tax' => $tax,
            'shipping' => $shipping,
            'flashSaleDiscount' => $flashSaleDiscounts['total_discount'],
            'flashSaleDiscountItems' => $flashSaleDiscounts['items_discount'],
            'flashSalePromotions' => $flashSaleDiscounts['flash_sales'],
            'bundleDiscount' => $bundleDiscount,
            'total' => $subtotal + $tax + $shipping - $flashSaleDiscounts['total_discount'] - $bundleDiscount,
            'item_count' => $items->sum('quantity'),
        ];
    }

    /**
     * Buy More, Save More — order-wide volume discount.
     * Applies the tier for the TOTAL quantity across all cart items to the
     * whole order value (default: 2+ items → 5% off, 3+ items → 10% off,
     * 4+ items → 15% off). A tier may optionally define a maxQty cap, in
     * which case the discount applies only while the total quantity is
     * within [minQty, maxQty].
     * Only applies when the offer is activated in Admin → Settings (bundleOfferEnabled).
     * Mirrors the frontend calcBundleDiscount in utils/constants.js.
     *
     * @param array $items  Each item needs 'quantity' and 'price'.
     */
    public function calculateBundleDiscount(array $items): float
    {
        // Offer must be activated from the admin panel; seeded inactive by default.
        if (!$this->isBundleOfferEnabled()) {
            return 0.0;
        }

        $tiers = $this->getBundleTiers();
        $totalQty = 0;
        $totalValue = 0.0;
        foreach ($items as $item) {
            // Missing quantity defaults to 1 on both FE (?? 1) and BE so the
            // tier threshold matches; a cart line always carries a qty >= 1.
            $qty = (int) ($item['quantity'] ?? 1);
            $price = (float) ($item['price'] ?? 0);
            $totalQty += $qty;
            $totalValue += $price * $qty;
        }
        $pct = $this->getBundleTierPercent($totalQty, $tiers);
        return round($totalValue * ($pct / 100), 2);
    }

    /**
     * Whether the bundle offer is active.
     * Requires ALL of: the global salesEnabled toggle, the bundleOfferEnabled toggle
     * (seeded 'false' — admin enables it from Admin → Settings → General), and the
     * optional date window (bundleOfferStartDate/bundleOfferEndDate, blank = no bound).
     * Mirrors the frontend isBundleOfferEnabled in utils/constants.js.
     */
    protected function isBundleOfferEnabled(): bool
    {
        $salesEnabled = (string) $this->settingsService->get('salesEnabled', 'true');
        if ($salesEnabled === 'false' || $salesEnabled === '0') {
            return false;
        }
        if ((string) $this->settingsService->get('bundleOfferEnabled', 'false') === 'false') {
            return false;
        }

        // Optional date window — offer only applies while today is within [start, end]
        // "Today" is evaluated in the store's configured timezone so it matches the
        // frontend check (utils/constants.js isBundleOfferEnabled) across timezones.
        $start = (string) $this->settingsService->get('bundleOfferStartDate', '');
        $end = (string) $this->settingsService->get('bundleOfferEndDate', '');
        if ($start !== '' || $end !== '') {
            $today = now($this->resolveStoreTimezone())->format('Y-m-d');
            if ($start !== '' && $today < $start) {
                return false;
            }
            if ($end !== '' && $today > $end) {
                return false;
            }
        }
        return true;
    }

    /**
     * Resolve the stored timezone setting (abbreviation like 'IST' or an IANA
     * name like 'Asia/Kolkata') to a valid IANA name for Carbon. Mirrors the
     * frontend TIMEZONE_MAP in utils/formatters.js.
     */
    protected function resolveStoreTimezone(): string
    {
        $tz = (string) $this->settingsService->get('timezone', 'UTC');
        if (str_contains($tz, '/')) {
            return $tz;
        }
        $map = [
            'IST' => 'Asia/Kolkata',
            'EST' => 'America/New_York',
            'CST' => 'America/Chicago',
            'MST' => 'America/Denver',
            'PST' => 'America/Los_Angeles',
            'GMT' => 'Europe/London',
            'CET' => 'Europe/Paris',
            'EET' => 'Europe/Helsinki',
            'AEST' => 'Australia/Sydney',
            'AEDT' => 'Australia/Sydney',
            'JST' => 'Asia/Tokyo',
            'KST' => 'Asia/Seoul',
            'CST_CN' => 'Asia/Shanghai',
            'HKT' => 'Asia/Hong_Kong',
            'SGT' => 'Asia/Singapore',
            'GST' => 'Asia/Dubai',
            'NZST' => 'Pacific/Auckland',
            'NZDT' => 'Pacific/Auckland',
            'BST' => 'Europe/London',
            'AST' => 'America/Halifax',
            'NST' => 'America/St_Johns',
            'AKST' => 'America/Anchorage',
            'HST' => 'Pacific/Honolulu',
        ];
        return $map[strtoupper($tz)] ?? 'UTC';
    }

    /**
     * Read the admin-configured bundle tiers (JSON list of
     * {minQty, discount, maxQty?}). Falls back to the default tiers if unset or
     * malformed. maxQty is optional and caps the per-product quantity window.
     */
    protected function getBundleTiers(): array
    {
        $raw = (string) $this->settingsService->get('bundleTiers', '');
        $tiers = json_decode($raw, true);
        if (!is_array($tiers) || empty($tiers)) {
            return [
                ['minQty' => 2, 'discount' => 5],
                ['minQty' => 3, 'discount' => 10],
                ['minQty' => 4, 'discount' => 15],
            ];
        }
        return array_values(array_filter($tiers, fn($t) => isset($t['minQty'])));
    }

    /**
     * Highest discount percent for a quantity among the tiers, honoring each
     * tier's optional maxQty cap against the TOTAL cart quantity: a tier
     * applies only while minQty <= qty <= maxQty (maxQty absent = open-ended).
     */
    protected function getBundleTierPercent(int $qty, array $tiers): int
    {
        $pct = 0;
        foreach ($tiers as $tier) {
            $minQty = (int) ($tier['minQty'] ?? 0);
            // Only a positive maxQty acts as a cap (mirrors the frontend parser,
            // which drops caps <= 0 and treats the tier as open-ended).
            $maxQty = isset($tier['maxQty']) && $tier['maxQty'] !== '' && (int) $tier['maxQty'] > 0
                ? (int) $tier['maxQty']
                : null;
            if ($qty >= $minQty && ($maxQty === null || $qty <= $maxQty)) {
                $pct = max($pct, (int) ($tier['discount'] ?? 0));
            }
        }
        return $pct;
    }

    public function calculateShipping(string $userId): array
    {
        $items = $this->cartRepository->getUserCart($userId);
        $subtotal = $items->sum(fn($item) => ($item->product?->price ?? 0) * $item->quantity);
        $freeShippingThreshold = $this->getFreeShippingThreshold();
        $standardShippingCost = $this->getStandardShippingCost();
        $standardCost = $subtotal >= $freeShippingThreshold ? 0 : $standardShippingCost;
        $expressCost = $subtotal >= $freeShippingThreshold ? 0 : $standardShippingCost + 5;

        return [
            'standard' => ['cost' => $standardCost, 'estimated_days' => '5-7'],
            'express' => ['cost' => $expressCost, 'estimated_days' => '2-3'],
            'free_threshold' => $freeShippingThreshold,
            'current_subtotal' => $subtotal,
            'free_shipping_remaining' => max(0, $freeShippingThreshold - $subtotal),
        ];
    }

    public function applyCoupon(string $code, float $subtotal): array
    {
        $coupon = $this->couponRepository->findActiveByCode($code);
        if (!$coupon) throw AppError::validation('Invalid or expired coupon');

        $discount = 0;
        if ($coupon->type === 'PERCENTAGE') {
            $discount = $subtotal * ($coupon->discount_value / 100);
            if ($coupon->max_discount) {
                $discount = min($discount, $coupon->max_discount);
            }
        } elseif ($coupon->type === 'FIXED') {
            $discount = $coupon->discount_value;
        }

        return [
            'coupon' => $coupon->toArray(),
            'discount' => $discount,
            'new_total' => $subtotal - $discount,
        ];
    }

    /**
     * Initiate a checkout session with cart items.
     */
    public function initiateCheckout(string $userId): array
    {
        $items = $this->cartRepository->getUserCart($userId);
        if ($items->isEmpty()) {
            throw AppError::validation('Cart is empty');
        }

        $subtotal = $items->sum(fn($item) => ($item->product?->price ?? 0) * $item->quantity);

        $itemsArray = $items->load('product.images')->toArray();

        // Map flat imageUrl onto each item for consistency
        foreach ($itemsArray as &$item) {
            $item['imageUrl'] = $item['product']['images'][0]['url'] ?? $item['image_url'] ?? null;
        }
        unset($item);

        return [
            'session_id' => (string) Str::uuid(),
            'items' => $itemsArray,
            'subtotal' => $subtotal,
            'status' => 'INITIATED',
        ];
    }

    /**
     * Calculate total from subtotal, tax, shipping, and discount.
     */
    public function calculateTotal(float $subtotal, float $tax = 0, float $shipping = 0, float $discount = 0): float
    {
        return max(0, $subtotal + $tax + $shipping - $discount);
    }

    /**
     * Process the checkout (prepare for payment).
     */
    public function processCheckout(array $sessionData): array
    {
        $total = $this->calculateTotal(
            $sessionData['subtotal'] ?? 0,
            $sessionData['tax'] ?? 0,
            $sessionData['shipping_cost'] ?? 0,
            $sessionData['discount'] ?? 0
        );

        return array_merge($sessionData, [
            'total' => $total,
            'status' => 'READY_FOR_PAYMENT',
        ]);
    }

    public function removeCoupon(): array
    {
        return ['message' => 'Coupon removed'];
    }
}
