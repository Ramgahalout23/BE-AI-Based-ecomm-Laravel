<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;

return new class extends Migration
{
    public function up()
    {
        Setting::updateOrCreate(
            ['key' => 'autoCancelUnpaidEnabled', 'module' => 'SITE'],
            ['value' => 'true']
        );
        Setting::updateOrCreate(
            ['key' => 'autoCancelUnpaidMinutes', 'module' => 'SITE'],
            ['value' => '45']
        );

        // SettingsRepository caches setting_{key} — clear so fresh values are read.
        Cache::forget('setting_autoCancelUnpaidEnabled');
        Cache::forget('setting_autoCancelUnpaidMinutes');
    }

    public function down()
    {
        Setting::where('module', 'SITE')
            ->whereIn('key', ['autoCancelUnpaidEnabled', 'autoCancelUnpaidMinutes'])
            ->delete();

        Cache::forget('setting_autoCancelUnpaidEnabled');
        Cache::forget('setting_autoCancelUnpaidMinutes');
    }
};
