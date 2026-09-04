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
        Schema::create('melted_gold_cards', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->decimal('buy_fixed', 10, 0)->unsigned()->default(0);
            $table->decimal('buy_percentage', 4,2)->unsigned()->default(0);
            $table->decimal('sell_fixed', 10, 0)->unsigned()->default(0);
            $table->decimal('sell_percentage', 4,2)->unsigned()->default(0);
            $table->unsignedTinyInteger('order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('melted_gold_cards');
    }
};
