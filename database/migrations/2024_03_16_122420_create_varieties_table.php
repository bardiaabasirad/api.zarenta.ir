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
        Schema::create('varieties', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(\App\Models\Product::class)->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignIdFor(\App\Models\Color::class)->nullable()->constrained()->cascadeOnUpdate()->nullOnDelete();
            $table->decimal('barcode', 6, 0)->unsigned()->unique()->default(null);
            $table->decimal('weight', 6,3)->unsigned();
            $table->decimal('size',7, 2)->unsigned()->nullable();
            $table->unsignedTinyInteger('count')->default(1);
            $table->decimal('gold_price', 8,0)->unsigned();
            $table->decimal('percentage_buy_wage', 4,2)->unsigned()->nullable();
            $table->decimal('tomans_buy_wage', 9,0)->unsigned()->nullable();
            $table->decimal('percentage_sell_wage', 4,2)->unsigned()->nullable();
            $table->decimal('tomans_sell_wage', 9,0)->unsigned()->nullable();
            $table->decimal('percentage_profit', 4,2)->unsigned()->nullable();
            $table->decimal('tomans_profit', 9,0)->unsigned()->nullable();
            $table->decimal('percentage_discount', 4,2)->unsigned()->nullable();
            $table->decimal('tomans_discount', 9,0)->unsigned()->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('varieties');
    }
};
