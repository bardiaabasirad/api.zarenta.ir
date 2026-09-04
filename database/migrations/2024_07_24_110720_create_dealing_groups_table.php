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
        Schema::create('dealing_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('display_mode', ['quotation','per_gram'])->default('quotation');
            $table->enum('tolerance_type', ['fixed_amount','percentage'])->default('fixed_amount');
            $table->json('products_settings')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dealing_groups');
    }
};
