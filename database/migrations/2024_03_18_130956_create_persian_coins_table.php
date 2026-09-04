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
        Schema::create('persian_coins', function (Blueprint $table) {
            $table->id();
            $table->decimal('weight', 6,3)->unsigned();
            $table->decimal('percentage_profit', 4, 2)->unsigned()->nullable();
            $table->decimal('tomans_profit', 8, 0)->unsigned()->nullable();
            $table->decimal('percentage_wage', 4, 2)->unsigned()->nullable();
            $table->decimal('tomans_wage', 8, 0)->unsigned()->nullable();
            $table->enum('status',['active','inactive'])->default('active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('persian_coins');
    }
};
