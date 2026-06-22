<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('seo', function (Blueprint $table) {
            // ── JSON-LD Structured Data ──
            $table->text('json_ld_product')->nullable()->after('canonical_url');
            $table->text('json_ld_organization')->nullable()->after('json_ld_product');
            $table->text('json_ld_breadcrumb')->nullable()->after('json_ld_organization');
            $table->text('json_ld_faq')->nullable()->after('json_ld_breadcrumb');
            $table->text('json_ld_website')->nullable()->after('json_ld_faq');

            // ── Hreflang ──
            $table->text('hreflang_tags')->nullable()->after('json_ld_website');

            // ── Breadcrumbs ──
            $table->text('breadcrumb_path')->nullable()->after('hreflang_tags');

            // ── Social / Extra Meta ──
            $table->string('facebook_pixel_id')->nullable()->after('breadcrumb_path');
            $table->string('google_analytics_id')->nullable()->after('facebook_pixel_id');
            $table->string('google_tag_manager_id')->nullable()->after('google_analytics_id');

            // ── Advanced Meta ──
            $table->string('robots_meta')->nullable()->after('google_tag_manager_id');
            $table->string('cache_control')->nullable()->after('robots_meta');
            $table->string('content_language')->nullable()->after('cache_control');

            // ── SEO Scoring / Audit ──
            $table->integer('seo_score')->nullable()->after('content_language');
            $table->text('seo_score_breakdown')->nullable()->after('seo_score');
            $table->timestamp('seo_last_audited_at')->nullable()->after('seo_score_breakdown');

            // ── Priority / Frequency for Sitemap ──
            $table->decimal('sitemap_priority', 2, 1)->default(0.5)->after('seo_last_audited_at');
            $table->string('sitemap_changefreq')->default('weekly')->after('sitemap_priority');

            // ── IndexNow ──
            $table->timestamp('indexnow_pushed_at')->nullable()->after('sitemap_changefreq');
            $table->boolean('indexnow_pending')->default(false)->after('indexnow_pushed_at');

            // ── Indexes ──
            $table->index('seo_score');
            $table->index('sitemap_priority');
        });

        // Add advanced SEO settings to settings table
        // Use Eloquent model to auto-generate UUIDs
        $settingModel = new \App\Models\Setting();
        $settings = [
            ['key' => 'seo_google_analytics_id', 'value' => '', 'module' => 'SEO'],
            ['key' => 'seo_google_tag_manager_id', 'value' => '', 'module' => 'SEO'],
            ['key' => 'seo_facebook_pixel_id', 'value' => '', 'module' => 'SEO'],
            ['key' => 'seo_organization_name', 'value' => '', 'module' => 'SEO'],
            ['key' => 'seo_organization_logo', 'value' => '', 'module' => 'SEO'],
            ['key' => 'seo_organization_url', 'value' => '', 'module' => 'SEO'],
            ['key' => 'seo_social_links', 'value' => '', 'module' => 'SEO'],
            ['key' => 'seo_hreflang_default', 'value' => 'en', 'module' => 'SEO'],
            ['key' => 'seo_enable_auto_schema', 'value' => 'true', 'module' => 'SEO'],
            ['key' => 'seo_enable_indexnow', 'value' => 'false', 'module' => 'SEO'],
            ['key' => 'seo_breadcrumb_separator', 'value' => '/', 'module' => 'SEO'],
            ['key' => 'seo_default_image', 'value' => '', 'module' => 'SEO'],
            ['key' => 'seo_twitter_handle', 'value' => '', 'module' => 'SEO'],
            ['key' => 'seo_auto_audit_enabled', 'value' => 'true', 'module' => 'SEO'],
            ['key' => 'seo_audit_schedule', 'value' => 'weekly', 'module' => 'SEO'],
        ];

        foreach ($settings as $setting) {
            \App\Models\Setting::updateOrCreate(
                ['key' => $setting['key'], 'module' => $setting['module']],
                ['value' => $setting['value']]
            );
        }
    }

    public function down()
    {
        Schema::table('seo', function (Blueprint $table) {
            $table->dropColumn([
                'json_ld_product', 'json_ld_organization', 'json_ld_breadcrumb',
                'json_ld_faq', 'json_ld_website', 'hreflang_tags', 'breadcrumb_path',
                'facebook_pixel_id', 'google_analytics_id', 'google_tag_manager_id',
                'robots_meta', 'cache_control', 'content_language',
                'seo_score', 'seo_score_breakdown', 'seo_last_audited_at',
                'sitemap_priority', 'sitemap_changefreq',
                'indexnow_pushed_at', 'indexnow_pending',
            ]);
        });

        \App\Models\Setting::where('module', 'SEO')
            ->whereIn('key', [
                'seo_google_analytics_id', 'seo_google_tag_manager_id', 'seo_facebook_pixel_id',
                'seo_organization_name', 'seo_organization_logo', 'seo_organization_url',
                'seo_social_links', 'seo_hreflang_default', 'seo_enable_auto_schema',
                'seo_enable_indexnow', 'seo_breadcrumb_separator', 'seo_default_image',
                'seo_twitter_handle', 'seo_auto_audit_enabled', 'seo_audit_schedule',
            ])
            ->delete();
    }
};
