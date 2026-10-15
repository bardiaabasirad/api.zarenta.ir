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

            $table->decimal('buy', 20, 0)->unsigned()->nullable();
            $table->decimal('sell', 20, 0)->unsigned()->nullable();

            $table->timestamp('time');
            $table->timestamps();

            // ۱. پوشش تمام کوئری‌های عمومی بر اساس فلز و فیلتر زمانی
            // (همچنین به عنوان ایندکس Foreign Key برای metal_item_id عمل می‌کند)
            $table->index(['metal_item_id', 'time'], 'idx_selected_metal_item_time');

            // ۲. پوشش تمام کوئری‌های استعلام منبع + فلز + بازه زمانی و مرتب‌سازی
            // توجه: InnoDB به صورت خودکار `id` را به انتهای این ایندکس الصاق می‌کند
            $table->index(
                ['price_source_id', 'metal_item_id', 'time'],
                'idx_source_item_time'
            );

            // ۳. کوئری زمان آخرین به‌روزرسانی بر اساس منبع قیمت
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
