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
        Schema::create('market_prices', function (Blueprint $table) {
            $table->id();
            $table->decimal('ounce', 8,0)->unsigned()->nullable();
            $table->decimal('price', 8,0)->unsigned();
            $table->decimal('emam_coin', 8,0)->unsigned()->nullable();
            $table->decimal('full_coin', 8,0)->unsigned()->nullable();
            $table->decimal('half_coin', 8,0)->unsigned()->nullable();
            $table->decimal('quarter_coin', 8,0)->unsigned()->nullable();
            $table->decimal('dollar', 8,0)->unsigned()->nullable();
            $table->decimal('euro', 8,0)->unsigned()->nullable();
            $table->decimal('derham', 8,0)->unsigned()->nullable();
            $table->timestamp('read_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('market_prices');
    }
};
