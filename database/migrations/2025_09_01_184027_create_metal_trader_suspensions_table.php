<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('metal_trader_suspensions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('metal_trader_id')
                ->constrained('metal_traders')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('suspended_by')
                ->nullable()
                ->constrained('admins')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('unsuspended_by')
                ->nullable()
                ->constrained('admins')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('block_reason_id')
                ->nullable()
                ->constrained('block_reasons')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->text('description')->nullable();

            $table->timestamp('suspended_at');
            $table->timestamp('unsuspended_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('metal_trader_suspensions');
    }
};
