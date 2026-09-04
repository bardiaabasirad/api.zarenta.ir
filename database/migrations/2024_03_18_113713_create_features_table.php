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
        Schema::create('features', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->string('values');
        });

        Schema::create('feature_product', function (Blueprint $table) {
            $table->foreignIdFor(\App\Models\Feature::class)->constrained()->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignIdFor(\App\Models\Product::class)->constrained()->cascadeOnDelete()->cascadeOnUpdate();
            $table->string('value');

            $table->primary(['feature_id','product_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('feature_product');
        Schema::dropIfExists('features');
    }
};
