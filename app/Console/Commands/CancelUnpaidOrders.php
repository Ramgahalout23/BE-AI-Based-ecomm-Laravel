<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\OrderService;
use App\Services\SettingsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CancelUnpaidOrders extends Command
{
    protected $signature = 'orders:cancel-unpaid
                            {--minutes= : Minimum minutes since order was created to auto-cancel (defaults to the autoCancelUnpaidMinutes setting)}
                            {--dry-run : Preview which orders would be cancelled without cancelling}';

    protected $description = 'Auto-cancel unpaid PENDING orders (abandoned online checkouts) and restore their stock';

    public function __construct(
        protected OrderService $orderService,
        protected SettingsService $settingsService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        // Feature toggle (Admin → Settings): when disabled, the scheduled run does
        // nothing. Passing --minutes explicitly (manual/admin run) bypasses the toggle.
        $minutesFlag = $this->option('minutes');
        $enabled = (string) $this->settingsService->get('autoCancelUnpaidEnabled', 'true');
        if ($minutesFlag === null && ($enabled === 'false' || $enabled === '0')) {
            $this->info('Auto-cancel of unpaid orders is disabled (autoCancelUnpaidEnabled=false). Skipping.');
            return Command::SUCCESS;
        }

        // Window: explicit --minutes flag wins, else the admin setting, else config.
        $minutes = $minutesFlag !== null
            ? (int) $minutesFlag
            : (int) $this->settingsService->get('autoCancelUnpaidMinutes', (int) config('orders.auto_cancel_minutes', 45));

        $cutoff = now()->subMinutes($minutes);

        $this->info("Scanning for unpaid PENDING orders older than {$minutes} minute(s)...");

        // PENDING = awaiting payment confirmation (COD orders are confirmed instantly,
        // so this only catches abandoned online/UPI/Razorpay checkouts). Defensively
        // exclude COD and already-completed payments so nothing real is ever cancelled.
        $orders = Order::with('payment')
            ->where('status', 'PENDING')
            ->where('created_at', '<', $cutoff)
            ->whereDoesntHave('payment', fn ($q) => $q->where('method', 'COD'))
            ->whereDoesntHave('payment', fn ($q) => $q->where('status', 'COMPLETED'))
            ->orderBy('created_at', 'asc')
            ->get();

        if ($orders->isEmpty()) {
            $this->info('No unpaid PENDING orders to cancel.');
            return Command::SUCCESS;
        }

        $this->info("Found {$orders->count()} unpaid PENDING order(s) older than {$minutes} minute(s).");

        $cancelled = 0;
        $errors = 0;

        foreach ($orders as $order) {
            $label = "order {$order->order_number} ({$order->id})";

            if ($dryRun) {
                $this->line("  [DRY-RUN] Would cancel {$label} created {$order->created_at}");
                $cancelled++;
                continue;
            }

            // Race guard: re-check the order is still unpaid right before cancelling,
            // so a payment that completed since the query snapshot is never cancelled.
            $order->refresh();
            if ($order->status !== 'PENDING') {
                $this->line("  – Skipped {$label}: no longer PENDING");
                continue;
            }
            if ($order->payment && $order->payment->status === 'COMPLETED') {
                $this->line("  – Skipped {$label}: payment already completed");
                continue;
            }

            try {
                $this->orderService->cancelOrder($order->id, "Auto-cancelled: payment not received within {$minutes} minutes.");
                $cancelled++;
                $this->line("  ✓ Cancelled {$label} — stock restored");
            } catch (\Exception $e) {
                $errors++;
                Log::error("[CancelUnpaidOrders] Failed to cancel {$label}: {$e->getMessage()}");
                $this->error("  ✗ Failed {$label}: {$e->getMessage()}");
            }
        }

        $this->newLine();
        $this->info("Done. Cancelled: {$cancelled}, Errors: {$errors}");

        return $errors > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
