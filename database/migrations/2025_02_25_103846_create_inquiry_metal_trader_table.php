<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inquiry_metal_trader', function (Blueprint $table) {
            $table->foreignId('metal_trader_id')
                ->nullable()
                ->constrained('metal_traders')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('inquiry_id')
                ->constrained('inquiries')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inquiry_metal_trader');
    }
};
