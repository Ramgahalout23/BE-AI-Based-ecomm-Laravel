<?php

namespace App\Repositories;

use App\Models\AbandonedCart;
use Illuminate\Database\Eloquent\Collection;

class AbandonedCartRepository extends BaseRepository
{
    protected function modelClass(): string
    {
        return AbandonedCart::class;
    }

    public function getUserAbandonedCarts(string $userId): Collection
    {
        return AbandonedCart::where('user_id', $userId)->latest()->get();
    }

    public function getActiveCarts(): Collection
    {
        return AbandonedCart::with('user')
            ->where('reminded', false)
            ->where('created_at', '<', now()->subHours(2))
            ->get();
    }

    public function markReminded(string $id): void
    {
        AbandonedCart::where('id', $id)->update(['reminded' => true, 'reminded_at' => now()]);
    }

    public function getRecoveryStats(): array
    {
        $total = AbandonedCart::count();
        $recovered = AbandonedCart::where('recovered', true)->count();
        return [
            'total' => $total,
            'recovered' => $recovered,
            'rate' => $total > 0 ? round(($recovered / $total) * 100, 2) : 0,
        ];
    }
}
