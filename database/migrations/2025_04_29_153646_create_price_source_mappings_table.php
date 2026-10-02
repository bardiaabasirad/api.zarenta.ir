<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_source_mappings', function (Blueprint $table) {
            $table->id();

            $table->foreignIdFor(\App\Models\PriceSource::class)
                ->nullable()
                ->constrained()
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->foreignIdFor(\App\Models\MetalItem::class)
                ->unique()
                ->constrained()
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->string('buy')->nullable();
            $table->string('buy_from_sell')->nullable();
            $table->string('sell')->nullable();
            $table->string('sell_from_buy')->nullable();
            $table->boolean('generate_buy_or_sell')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_source_mappings');
    }
};
