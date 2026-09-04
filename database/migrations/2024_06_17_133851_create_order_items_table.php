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
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(\App\Models\Order::class)->constrained()->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignIdFor(\App\Models\Product::class)->nullable()->constrained()->nullOnDelete()->cascadeOnUpdate();
            $table->foreignIdFor(\App\Models\Variety::class)->nullable()->constrained()->nullOnDelete()->cascadeOnUpdate();
            $table->text('product');
            $table->decimal('count', 4,0)->unsigned();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
