<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('reviews', function (Blueprint $table) {
            // MySQL-only: the FK on user_id blocks making the column nullable, so
            // drop it first. SQLite stores FKs inline at table creation and
            // re-creates the table for column changes, so dropForeign would throw
            // — guard it so the test suite (SQLite :memory:) can migrate.
            if (Schema::getConnection()->getDriverName() === 'mysql') {
                $table->dropForeign(['user_id']);
            }
            $table->uuid('user_id')->nullable()->change();
        });
    }

    public function down()
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->uuid('user_id')->nullable(false)->change();
            $table->foreign('user_id')->references('id')->on('users');
        });
    }
};
