<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('metal_item_price_source', function (Blueprint $table) {
            $table->foreignId('price_source_id')
                ->constrained('price_sources')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->foreignId('metal_item_id')
                ->constrained('metal_items')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            // شناسه‌های مجزا برای استعلام نرخ و ثبت سفارش در وب‌سرویس‌های خارجی
            $table->string('rate_external_identifier', 191)->nullable();
            $table->string('order_external_identifier', 191)->nullable();

            $table->timestamps();

            // کلید اصلی مرکب
            $table->primary(['price_source_id', 'metal_item_id']);

            // ایندکس ترکیبی برای جستجوی سریع بر اساس منبع قیمت و شناسه استعلام نرخ
            $table->index(
                ['price_source_id', 'rate_external_identifier'],
                'idx_source_rate_external_identifier'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('metal_item_price_source');
    }
};
