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
        Schema::create('directory_product', function (Blueprint $table) {
            $table->foreignIdFor(\App\Models\Directory::class)->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignIdFor(\App\Models\Product::class)->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->unsignedInteger('order');
            $table->primary(['directory_id','product_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('directory_product');
    }
};
