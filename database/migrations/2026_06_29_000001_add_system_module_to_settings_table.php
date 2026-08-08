<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // ENUM widening is MySQL-specific syntax. SQLite's settings.module is a
        // plain string column, so there is nothing to modify there.
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }
        DB::statement("ALTER TABLE settings MODIFY COLUMN module ENUM(
            'SITE', 'THEME', 'PAYMENT', 'SHIPPING', 'TAX', 'SMTP', 'SOCIAL',
            'CURRENCY', 'LANGUAGE', 'CONTACT', 'WEBSOCKET', 'MARKETING', 'ADS',
            'SYSTEM'
        ) NOT NULL");
    }

    public function down()
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }
        DB::statement("ALTER TABLE settings MODIFY COLUMN module ENUM(
            'SITE', 'THEME', 'PAYMENT', 'SHIPPING', 'TAX', 'SMTP', 'SOCIAL',
            'CURRENCY', 'LANGUAGE', 'CONTACT', 'WEBSOCKET', 'MARKETING', 'ADS'
        ) NOT NULL");
    }
};
