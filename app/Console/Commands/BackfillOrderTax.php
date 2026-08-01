<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Repositories\AdminRepository;
use App\Services\SettingsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class BackfillOrderTax extends Command
{
    protected $signature = 'orders:backfill-tax
        {--dry-run : Preview which orders would be updated without writing to the database}
        {--rate= : Override the tax rate percentage (defaults to the taxRate setting)}';

    protected $description = 'Backfill tax on historical orders recorded with tax = 0 (exclusive tax mode only). Recomputes totals: total = subtotal + tax + shipping - discount.';

    public function handle(SettingsService $settingsService): int
    {
        $taxCalculation = (string) $settingsService->get('taxCalculation', 'inclusive');
        if ($taxCalculation !== 'exclusive') {
            $this->info("Skipping: tax calculation is '{$taxCalculation}', not 'exclusive'. Tax is legitimately 0 in inclusive mode — nothing to backfill.");
            return Command::SUCCESS;
        }

        $rate = $this->option('rate') !== null
            ? (float) $this->option('rate')
            : (float) $settingsService->get('taxRate', '18.0');
        $dryRun = (bool) $this->option('dry-run');

        if ($rate <= 0) {
            $this->error('Tax rate must be greater than zero.');
            return Command::FAILURE;
        }

        // Historical orders with no tax recorded (tax = 0 or NULL)
        $orders = Order::whereNull('tax')->orWhere('tax', 0)->get();

        if ($orders->isEmpty()) {
            $this->info('No orders found with zero tax.');
            return Command::SUCCESS;
        }

        $this->info("Found {$orders->count()} order(s) with zero tax (rate: {$rate}%).");
        if ($dryRun) {
            $this->warn('── DRY RUN — no changes will be made ──');
        }

        $updated = 0;
        foreach ($orders as $order) {
            $subtotal = (float) $order->subtotal;
            $tax = round($subtotal * $rate / 100, 2);
            $total = round($subtotal + $tax + (float) $order->shipping_cost - (float) $order->discount, 2);

            if ($dryRun) {
                $this->line("  [DRY] {$order->order_number}: tax {$order->tax} -> {$tax}, total {$order->total} -> {$total}");
                $updated++;
                continue;
            }

            $order->update(['tax' => $tax, 'total' => $total]);
            // Invalidate the cached order payload so the order detail page shows the new tax line
            Cache::forget('order_' . $order->id);
            $this->line("  [UPDATED] {$order->order_number}: tax {$order->tax} -> {$tax}, total -> {$total}");
            $updated++;
        }

        if (!$dryRun) {
            // Totals changed — revenue/dashboard metrics must reflect the backfilled amounts
            app(AdminRepository::class)->clearDashboardCache();
        }

        $this->info("Done: {$updated} order(s) " . ($dryRun ? 'would be updated' : 'updated') . '.');
        Log::info("[BackfillOrderTax] " . ($dryRun ? 'DRY RUN' : 'Completed') . " — {$updated} order(s) at {$rate}% rate");

        return Command::SUCCESS;
    }
}
