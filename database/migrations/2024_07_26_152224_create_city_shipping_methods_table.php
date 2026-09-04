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
        Schema::create('city_shipping_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(\App\Models\City::class)->constrained()->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignIdFor(\App\Models\ShippingMethod::class)->constrained()->cascadeOnDelete()->cascadeOnUpdate();
            $table->decimal('shipping_cost', 7,0);
            $table->enum('status', ['active','inactive'])->default('active');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('city_shipping_methods');
    }
};
