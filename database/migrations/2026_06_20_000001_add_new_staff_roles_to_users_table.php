<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ENUM widening is MySQL-specific syntax. SQLite's users.role is a plain
        // string column, so there is nothing to modify there.
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('SUPER_ADMIN', 'ADMIN', 'MANAGER', 'CUSTOMER', 'SUPPORT_AGENT', 'FINANCE') DEFAULT 'CUSTOMER'");
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('SUPER_ADMIN', 'ADMIN', 'MANAGER', 'CUSTOMER') DEFAULT 'CUSTOMER'");
    }
};
