<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('promotions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('type', ['BANNER', 'POPUP', 'FLASH_SALE', 'NEWSLETTER', 'LOYALTY_REWARD', 'SEASONAL', 'PRODUCT_LAUNCH']);
            $table->string('image_url')->nullable();
            $table->string('link_url')->nullable();
            $table->decimal('discount', 10, 2)->nullable();
            $table->enum('status', ['DRAFT', 'SCHEDULED', 'ACTIVE', 'PAUSED', 'EXPIRED'])->default('DRAFT');
            $table->dateTime('start_date')->nullable();
            $table->dateTime('end_date')->nullable();
            $table->integer('priority')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('show_on_mobile')->default(true);
            $table->boolean('show_on_desktop')->default(true);
            $table->decimal('min_purchase', 10, 2)->nullable();
            $table->decimal('max_discount', 10, 2)->nullable();
            $table->string('coupon_code')->nullable()->unique();
            $table->string('created_by')->nullable();
            $table->timestamps();

            $table->index('type');
            $table->index('status');
            $table->index(['start_date', 'end_date']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('promotions');
    }
};
