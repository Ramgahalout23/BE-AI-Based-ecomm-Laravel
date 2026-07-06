<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add indexes on frequently-queried columns across all tables.
     *
     * These indexes target the slow endpoints identified in the performance audit:
     * - Wishlist queries: user_id, product_id (composite unique)
     * - Notification queries: user_id, is_read, type
     * - Tracking/page view queries: user_id, session_id, url, created_at
     * - User sessions: user_id, is_active, start_time
     * - User events: user_id, session_id, event_type
     * - Banner queries: is_active, type, start_date, end_date
     * - Product queries: status, is_featured, category_id, brand_id, created_at
     * - Pivot tables: promotion_product, promotion_category
     * - Translation queries: language_code, group (composite)
     * - Currency queries: is_active, is_default
     * - Category queries: parent_id
     * - Setting queries: key (unique)
     */
    public function up(): void
    {
        // ── Wishlist Items ──
        Schema::table('wishlist_items', function (Blueprint $table) {
            $table->index('user_id', 'idx_wishlist_user_id');
            $table->index('product_id', 'idx_wishlist_product_id');
            $table->unique(['user_id', 'product_id'], 'uq_wishlist_user_product');
        });

        // ── Notifications ──
        Schema::table('notifications', function (Blueprint $table) {
            $table->index('user_id', 'idx_notifications_user_id');
            $table->index('is_read', 'idx_notifications_is_read');
            $table->index('type', 'idx_notifications_type');
            $table->index(['user_id', 'is_read'], 'idx_notifications_user_unread');
        });

        // ── Page Views ──
        Schema::table('page_views', function (Blueprint $table) {
            $table->index('user_id', 'idx_pageviews_user_id');
            $table->index('session_id', 'idx_pageviews_session_id');
            $table->index('url', 'idx_pageviews_url');
            $table->index('created_at', 'idx_pageviews_created_at');
            $table->index(['user_id', 'created_at'], 'idx_pageviews_user_date');
        });

        // ── User Sessions ──
        Schema::table('user_sessions', function (Blueprint $table) {
            $table->index('user_id', 'idx_sessions_user_id');
            $table->index('session_id', 'idx_sessions_session_id');
            $table->index('is_active', 'idx_sessions_is_active');
            $table->index(['is_active', 'start_time'], 'idx_sessions_active_start');
        });

        // ── User Events ──
        Schema::table('user_events', function (Blueprint $table) {
            $table->index('user_id', 'idx_events_user_id');
            $table->index('session_id', 'idx_events_session_id');
            $table->index('event_type', 'idx_events_event_type');
            $table->index(['user_id', 'event_type'], 'idx_events_user_type');
        });

        // ── Banners ──
        Schema::table('banners', function (Blueprint $table) {
            $table->index('is_active', 'idx_banners_is_active');
            $table->index('type', 'idx_banners_type');
            $table->index('position', 'idx_banners_position');
            $table->index(['is_active', 'type', 'start_date', 'end_date'], 'idx_banners_active_type_date');
        });

        // ── Products ──
        Schema::table('products', function (Blueprint $table) {
            $table->index('status', 'idx_products_status');
            $table->index('is_featured', 'idx_products_featured');
            $table->index('category_id', 'idx_products_category_id');
            $table->index('brand_id', 'idx_products_brand_id');
            $table->index(['status', 'is_featured'], 'idx_products_published_featured');
            $table->index(['status', 'created_at'], 'idx_products_published_date');
            $table->index('view_count', 'idx_products_view_count');
        });

        // ── Promotions ──
        Schema::table('promotions', function (Blueprint $table) {
            $table->index('is_active', 'idx_promotions_is_active');
            $table->index('status', 'idx_promotions_status');
            $table->index('type', 'idx_promotions_type');
            $table->index(['is_active', 'end_date'], 'idx_promotions_active_end');
            $table->index('coupon_code', 'idx_promotions_coupon_code');
        });

        // ── Pivot Tables ──
        Schema::table('promotion_product', function (Blueprint $table) {
            $table->index('promotion_id', 'idx_promo_product_promotion_id');
            $table->index('product_id', 'idx_promo_product_product_id');
        });

        Schema::table('promotion_category', function (Blueprint $table) {
            $table->index('promotion_id', 'idx_promo_cat_promotion_id');
            $table->index('category_id', 'idx_promo_cat_category_id');
        });

        // ── Translations ──
        Schema::table('translations', function (Blueprint $table) {
            $table->index('language_code', 'idx_translations_lang_code');
            $table->index('group', 'idx_translations_group');
            $table->index(['language_code', 'group'], 'idx_translations_lang_group');
        });

        // ── Currencies ──
        Schema::table('currency_exchange_rates', function (Blueprint $table) {
            $table->index('is_active', 'idx_currencies_is_active');
            $table->index('is_default', 'idx_currencies_is_default');
            $table->index('code', 'idx_currencies_code');
        });

        // ── Categories ──
        Schema::table('categories', function (Blueprint $table) {
            $table->index('parent_id', 'idx_categories_parent_id');
            $table->index('is_active', 'idx_categories_is_active');
            $table->index('slug', 'idx_categories_slug');
        });

        // ── Brands ──
        Schema::table('brands', function (Blueprint $table) {
            $table->index('slug', 'idx_brands_slug');
        });

        // ── Settings ──
        Schema::table('settings', function (Blueprint $table) {
            $table->unique('key', 'uq_settings_key');
            $table->index('module', 'idx_settings_module');
        });

        // ── Languages ──
        Schema::table('languages', function (Blueprint $table) {
            $table->index('is_active', 'idx_languages_is_active');
            $table->index('is_default', 'idx_languages_is_default');
            $table->index('code', 'idx_languages_code');
        });

        // ── Pages ──
        Schema::table('pages', function (Blueprint $table) {
            $table->index('slug', 'idx_pages_slug');
            $table->index('is_published', 'idx_pages_is_published');
        });

        // ── Product Variants ──
        Schema::table('product_variants', function (Blueprint $table) {
            $table->index('product_id', 'idx_variants_product_id');
            $table->index('sku', 'idx_variants_sku');
        });
    }

    /**
     * Reverse the indexes.
     */
    public function down(): void
    {
        // Wishlist Items
        Schema::table('wishlist_items', function (Blueprint $table) {
            $table->dropIndex('idx_wishlist_user_id');
            $table->dropIndex('idx_wishlist_product_id');
            $table->dropUnique('uq_wishlist_user_product');
        });

        // Notifications
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex('idx_notifications_user_id');
            $table->dropIndex('idx_notifications_is_read');
            $table->dropIndex('idx_notifications_type');
            $table->dropIndex('idx_notifications_user_unread');
        });

        // Page Views
        Schema::table('page_views', function (Blueprint $table) {
            $table->dropIndex('idx_pageviews_user_id');
            $table->dropIndex('idx_pageviews_session_id');
            $table->dropIndex('idx_pageviews_url');
            $table->dropIndex('idx_pageviews_created_at');
            $table->dropIndex('idx_pageviews_user_date');
        });

        // User Sessions
        Schema::table('user_sessions', function (Blueprint $table) {
            $table->dropIndex('idx_sessions_user_id');
            $table->dropIndex('idx_sessions_session_id');
            $table->dropIndex('idx_sessions_is_active');
            $table->dropIndex('idx_sessions_active_start');
        });

        // User Events
        Schema::table('user_events', function (Blueprint $table) {
            $table->dropIndex('idx_events_user_id');
            $table->dropIndex('idx_events_session_id');
            $table->dropIndex('idx_events_event_type');
            $table->dropIndex('idx_events_user_type');
        });

        // Banners
        Schema::table('banners', function (Blueprint $table) {
            $table->dropIndex('idx_banners_is_active');
            $table->dropIndex('idx_banners_type');
            $table->dropIndex('idx_banners_position');
            $table->dropIndex('idx_banners_active_type_date');
        });

        // Products
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('idx_products_status');
            $table->dropIndex('idx_products_featured');
            $table->dropIndex('idx_products_category_id');
            $table->dropIndex('idx_products_brand_id');
            $table->dropIndex('idx_products_published_featured');
            $table->dropIndex('idx_products_published_date');
            $table->dropIndex('idx_products_view_count');
        });

        // Promotions
        Schema::table('promotions', function (Blueprint $table) {
            $table->dropIndex('idx_promotions_is_active');
            $table->dropIndex('idx_promotions_status');
            $table->dropIndex('idx_promotions_type');
            $table->dropIndex('idx_promotions_active_end');
            $table->dropIndex('idx_promotions_coupon_code');
        });

        // Pivot Tables
        Schema::table('promotion_product', function (Blueprint $table) {
            $table->dropIndex('idx_promo_product_promotion_id');
            $table->dropIndex('idx_promo_product_product_id');
        });

        Schema::table('promotion_category', function (Blueprint $table) {
            $table->dropIndex('idx_promo_cat_promotion_id');
            $table->dropIndex('idx_promo_cat_category_id');
        });

        // Translations
        Schema::table('translations', function (Blueprint $table) {
            $table->dropIndex('idx_translations_lang_code');
            $table->dropIndex('idx_translations_group');
            $table->dropIndex('idx_translations_lang_group');
        });

        // Currencies
        Schema::table('currency_exchange_rates', function (Blueprint $table) {
            $table->dropIndex('idx_currencies_is_active');
            $table->dropIndex('idx_currencies_is_default');
            $table->dropIndex('idx_currencies_code');
        });

        // Categories
        Schema::table('categories', function (Blueprint $table) {
            $table->dropIndex('idx_categories_parent_id');
            $table->dropIndex('idx_categories_is_active');
            $table->dropIndex('idx_categories_slug');
        });

        // Brands
        Schema::table('brands', function (Blueprint $table) {
            $table->dropIndex('idx_brands_slug');
        });

        // Settings
        Schema::table('settings', function (Blueprint $table) {
            $table->dropUnique('uq_settings_key');
            $table->dropIndex('idx_settings_module');
        });

        // Languages
        Schema::table('languages', function (Blueprint $table) {
            $table->dropIndex('idx_languages_is_active');
            $table->dropIndex('idx_languages_is_default');
            $table->dropIndex('idx_languages_code');
        });

        // Pages
        Schema::table('pages', function (Blueprint $table) {
            $table->dropIndex('idx_pages_slug');
            $table->dropIndex('idx_pages_is_published');
        });

        // Product Variants
        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropIndex('idx_variants_product_id');
            $table->dropIndex('idx_variants_sku');
        });
    }
};
