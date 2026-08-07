<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // ── Backup Scheduler ──
        $schedule->command('backup:run')->everyMinute();

        // ── Ad Campaign Scheduler ──
        $schedule->command('ads:process-scheduled')->hourly();

        // ── Marketing Campaign Scheduler ──
        $schedule->command('campaigns:process-scheduled')->everyFiveMinutes();

        // ── Maintenance Schedule Check ──
        $schedule->command('maintenance:check-schedule')->everyMinute();

        // ── Daily Analytics Aggregation ──
        // Aggregate the last 2 days every hour so the summary table stays fresh
        // (yesterday is re-aggregated too, covering gaps if the scheduler was
        // down). All-time dashboard metrics, revenue comparison and customer
        // growth read from this table, so keeping it current keeps the admin
        // dashboard fast and accurate. The dashboard metrics are cached 300s.
        $schedule->command('analytics:aggregate-daily --days=2')->hourly();

        // ── Guest User Cleanup (delete/anonymize placeholder accounts) ──
        $schedule->command('guest-users:cleanup --days=30')->dailyAt('03:00');

        // ── Export File Cleanup (delete export CSVs older than 30 days) ──
        $schedule->command('exports:cleanup --days=30')->dailyAt('03:30');

        // ── Abandoned Cart Reminders ──
        // Send email + DB notification reminders for carts abandoned >2 hours
        $schedule->command('abandoned-carts:process --hours=2')->everyFifteenMinutes();

        // ── Unpaid Order Cleanup ──
        // Auto-cancel PENDING (unpaid online checkout) orders older than the
        // admin-configurable window (Admin → Settings, autoCancelUnpaidMinutes,
        // default 45 min) and restore their stock, so abandoned Razorpay/UPI
        // payments don't sit in limbo forever or hold inventory hostage. The
        // command reads the enable toggle + window from settings itself (no flag
        // needed here), and the countdown shown to customers reads the same
        // settings — so frontend, scheduler, and admin control stay in sync.
        $schedule->command('orders:cancel-unpaid')
            ->everyFiveMinutes()
            ->withoutOverlapping();
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
