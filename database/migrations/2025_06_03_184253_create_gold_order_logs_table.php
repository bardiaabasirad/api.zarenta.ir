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
        Schema::create('gold_order_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(\App\Models\GoldOrder::class)->constrained()->cascadeOnDelete()->cascadeOnUpdate();
            $table->unsignedBigInteger('loggable_id')->nullable();
            $table->string('loggable_type')->nullable();
            $table->text('new_values')->nullable();
            $table->text('old_values')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gold_order_logs');
    }
};
