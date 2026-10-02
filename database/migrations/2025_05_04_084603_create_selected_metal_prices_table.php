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
        Schema::create('selected_metal_prices', function (Blueprint $table) {
            $table->id();

            $table->foreignId('price_source_id')
                ->nullable()
                ->constrained('price_sources')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->foreignId('metal_item_id')
                ->constrained('metal_items')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->string('buy')->nullable();
            $table->string('sell')->nullable();

            $table->timestamp('time');
            $table->timestamps();

            // ایندکس‌های پایه و عمومی بر اساس آیتم فلز
            $table->index(['metal_item_id', 'id']);
            $table->index(['metal_item_id', 'time'], 'idx_selected_metal_item_time');

            // ایندکس‌های اختصاصی سرویس استعلام و مرتب‌سازی نرخ‌ها
            // ۱. کوئری دریافت آخرین محصولات: WHERE price_source_id = X AND id IN (...)
            $table->index(
                ['price_source_id', 'metal_item_id', 'id'],
                'idx_source_item_id'
            );

            // ۲. کوئری دریافت قیمت محصول و مرتب‌سازی: WHERE price_source_id = X AND metal_item_id = Y ORDER BY time DESC, id DESC
            $table->index(
                ['price_source_id', 'metal_item_id', 'time', 'id'],
                'idx_source_item_time_id'
            );

            // ۳. کوئری زمان آخرین به‌روزرسانی: WHERE price_source_id = X ORDER BY updated_at DESC
            $table->index(
                ['price_source_id', 'updated_at'],
                'idx_source_updated'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('selected_metal_prices');
    }
};
