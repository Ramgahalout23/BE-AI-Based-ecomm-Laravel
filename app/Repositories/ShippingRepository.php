<?php

namespace App\Repositories;

use App\Models\Shipping;
use App\Models\ShippingZone;
use App\Models\ShippingRate;

class ShippingRepository extends BaseRepository
{
    protected function modelClass(): string
    {
        return Shipping::class;
    }

    public function findByOrder(string $orderId): ?Shipping
    {
        return Shipping::where('order_id', $orderId)->first();
    }

    public function getAllZones(): \Illuminate\Database\Eloquent\Collection
    {
        return ShippingZone::with('rates')->get();
    }

    public function findByTrackingNumber(string $trackingNumber): ?Shipping
    {
        return Shipping::where('tracking_number', $trackingNumber)->first();
    }

    public function calculateRate(string $zoneId, float $weight = 0, float $subtotal = 0): ?float
    {
        $zone = ShippingZone::with('rates')->find($zoneId);
        if (!$zone) return null;

        // Find applicable rate
        $rate = $zone->rates()
            ->where(function ($q) use ($weight) {
                $q->whereNull('max_weight')->orWhere('max_weight', '>=', $weight);
            })
            ->orderBy('base_rate')
            ->first();

        return $rate ? $rate->base_rate : null;
    }
}
