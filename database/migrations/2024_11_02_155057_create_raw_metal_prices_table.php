<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('raw_metal_prices', function (Blueprint $table) {
            $table->id();

            $table->foreignId('price_source_id')
                ->constrained('price_sources')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->foreignId('metal_item_id')
                ->constrained('metal_items')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->string('buy')->nullable();
            $table->string('sell')->nullable();

            $table->timestamp('time');
            $table->timestamps();

            // index های موجود/اضافه‌شده در تاریخچه
            $table->index(['metal_item_id', 'created_at'], 'idx_metal_item_created_at');
            $table->index(['metal_item_id', 'time'], 'idx_raw_metal_item_time');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('raw_metal_prices');
    }
};
