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
        Schema::create('metal_item_auto_order_settings', function (Blueprint $table) {
            $table->id();

            $table->foreignIdFor(\App\Models\MetalItem::class)
                ->unique()
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignIdFor(\App\Models\PriceSource::class)
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->boolean('is_enabled')->default(false);

            $table->decimal('min_auto_buy_weight', 10, 3)->nullable();
            $table->decimal('max_auto_buy_weight', 10, 3)->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('metal_item_auto_order_settings');
    }
};
