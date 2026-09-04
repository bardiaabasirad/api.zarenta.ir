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
        Schema::create('properties', function (Blueprint $table) {
            $table->id();
            $table->string('title', 100);
            $table->text('values')->nullable();
        });

        Schema::create('product_property', function (Blueprint $table) {
            $table->foreignIdFor(\App\Models\Product::class)->constrained()->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignIdFor(\App\Models\Property::class)->constrained()->cascadeOnDelete()->cascadeOnUpdate();
            $table->text('values');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_property');
        Schema::dropIfExists('properties');
    }
};
