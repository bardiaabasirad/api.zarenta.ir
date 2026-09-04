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
        Schema::create('metal_items', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(\App\Models\MetalItemGroup::class)
                ->constrained()
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->string('title', 191)->unique();
            $table->string('kimia_product_id', 191)->nullable();
            $table->boolean('is_buy_active')->default(true);
            $table->boolean('is_sell_active')->default(true);
            $table->decimal('equivalent_to', 10,3)->unsigned()->default(1);
            $table->decimal('purity', 3,0)->default(750);
            $table->enum('unit', ['gram','count'])->default('gram');
            $table->decimal('price_change_threshold', 10,0)->default(0);
            $table->decimal('buy_sell_spread', 10,0)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('metal_items');
    }
};
