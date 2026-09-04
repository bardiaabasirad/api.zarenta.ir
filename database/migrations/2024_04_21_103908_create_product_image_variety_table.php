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
        Schema::create('product_image_variety', function (Blueprint $table) {
            $table->foreignIdFor(\App\Models\ProductImage::class)->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignIdFor(\App\Models\Variety::class)->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->unsignedTinyInteger('orders');

            $table->primary(['product_image_id','variety_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_image_variety');
    }
};
