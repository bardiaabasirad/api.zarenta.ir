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
        Schema::create('reference_markets', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(\App\Models\ReferenceChannel::class)
                ->nullable()
                ->constrained()
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->enum('type', ['tomorrow','day_after_tomorrow','gold_coin_86','gold_half_coin_86','gold_quarter_coin_86','gold_coin_old_version'])->default('tomorrow');
            $table->string('buy')->nullable();
            $table->string('buy_from_sell')->nullable();
            $table->string('sell')->nullable();
            $table->string('sell_from_buy')->nullable();
            $table->boolean('generate_buy_or_sell')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reference_markets');
    }
};
