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
        Schema::create('board_coins', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(\App\Models\MetalItem::class)
                ->constrained()
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->unsignedInteger('sort_order');
            $table->decimal('buy_tolerance', 12, 0)->nullable();
            $table->decimal('sell_tolerance', 12, 0)->nullable();
            $table->boolean('is_featured')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('board_coins');
    }
};
