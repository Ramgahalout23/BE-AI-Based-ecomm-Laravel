<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add missing indexes identified from the slow admin query audit.
     *
     * Existing coverage already includes orders(status), orders(created_at),
     * payments(method), payments(created_at), products(status/view_count),
     * users(created_at), reviews(created_at) etc. — this batch fills the gaps:
     *
     *  1. product_variants.quantity          — AdminRepository::getLowStockVariants()
     *     WHERE quantity <= 5 ORDER BY quantity LIMIT 20 (only a composite
     *     (product_id, quantity) index existed — unusable for a bare quantity filter)
     *
     *  2. inventories.available_quantity     — low-stock dashboard count / inventory page
     *     (WHERE available_quantity <= 5)
     *
     *  3. orders(status, total)              — covering index for status-filtered
     *     SUM/MAX(total): getOrderRevenueStats(), getOrderStatusDistribution(),
     *     getDashboardMetrics() all-time revenue
     *
     *  4. payments(method, created_at)       — getPaymentMethodStats() /
     *     getPaymentMethodTrends() GROUP BY method + date
     *
     *  5-8. created_at on support_tickets, abandoned_carts, coupons, promotions
     *     — latest() ordering in getTickets(), getAbandonedCarts(), getCoupons(),
     *     getPromotions() previously forced a full-table sort.
     */
    private function indexExists(string $table, string $indexName): bool
    {
        try {
            $result = DB::select("
                SELECT COUNT(*) as cnt
                FROM information_schema.STATISTICS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = ?
                  AND INDEX_NAME = ?
            ", [$table, $indexName]);
            return !empty($result) && $result[0]->cnt > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function up(): void
    {
        // MySQL-only: the information_schema existence check and the targeted
        // admin-query indexes are meaningless (and unsupported via Schema::table
        // on Laravel 9 SQLite). Skip cleanly on other drivers.
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        // ── 1. product_variants.quantity ──
        if (Schema::hasTable('product_variants') && !$this->indexExists('product_variants', 'product_variants_quantity_index')) {
            Schema::table('product_variants', function (Blueprint $table) {
                $table->index('quantity');
            });
        }

        // ── 2. inventories.available_quantity ──
        if (Schema::hasTable('inventories') && !$this->indexExists('inventories', 'inventories_available_quantity_index')) {
            Schema::table('inventories', function (Blueprint $table) {
                $table->index('available_quantity');
            });
        }

        // ── 3. orders(status, total) covering index ──
        if (Schema::hasTable('orders') && !$this->indexExists('orders', 'orders_status_total_index')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->index(['status', 'total'], 'orders_status_total_index');
            });
        }

        // ── 4. payments(method, created_at) ──
        if (Schema::hasTable('payments') && !$this->indexExists('payments', 'payments_method_created_index')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->index(['method', 'created_at'], 'payments_method_created_index');
            });
        }

        // ── 5. support_tickets.created_at ──
        if (Schema::hasTable('support_tickets') && !$this->indexExists('support_tickets', 'support_tickets_created_at_index')) {
            Schema::table('support_tickets', function (Blueprint $table) {
                $table->index('created_at');
            });
        }

        // ── 6. abandoned_carts.created_at ──
        if (Schema::hasTable('abandoned_carts') && !$this->indexExists('abandoned_carts', 'abandoned_carts_created_at_index')) {
            Schema::table('abandoned_carts', function (Blueprint $table) {
                $table->index('created_at');
            });
        }

        // ── 7. coupons.created_at ──
        if (Schema::hasTable('coupons') && !$this->indexExists('coupons', 'coupons_created_at_index')) {
            Schema::table('coupons', function (Blueprint $table) {
                $table->index('created_at');
            });
        }

        // ── 8. promotions.created_at ──
        if (Schema::hasTable('promotions') && !$this->indexExists('promotions', 'promotions_created_at_index')) {
            Schema::table('promotions', function (Blueprint $table) {
                $table->index('created_at');
            });
        }
    }

    public function down(): void
    {
        $indexes = [
            'product_variants' => ['product_variants_quantity_index'],
            'inventories'      => ['inventories_available_quantity_index'],
            'orders'           => ['orders_status_total_index'],
            'payments'         => ['payments_method_created_index'],
            'support_tickets'  => ['support_tickets_created_at_index'],
            'abandoned_carts'  => ['abandoned_carts_created_at_index'],
            'coupons'          => ['coupons_created_at_index'],
            'promotions'       => ['promotions_created_at_index'],
        ];

        foreach ($indexes as $table => $indexNames) {
            if (!Schema::hasTable($table)) continue;
            foreach ($indexNames as $index) {
                if ($this->indexExists($table, $index)) {
                    try {
                        Schema::table($table, function (Blueprint $t) use ($index) {
                            $t->dropIndex($index);
                        });
                    } catch (\Exception $e) {
                        // Ignore errors on rollback
                    }
                }
            }
        }
    }
};
