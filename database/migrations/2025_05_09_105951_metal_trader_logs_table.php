<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('metal_trader_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('metal_trader_id')
                ->constrained('metal_traders')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            // جایگزین loggable_id و loggable_type + ایجاد ایندکس
            $table->morphs('loggable');

            $table->text('new_values')->nullable();
            $table->text('old_values')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('metal_trader_logs');
    }
};
