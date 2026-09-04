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
        Schema::create('selected_auto_order_exchanges', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(\App\Models\AutoOrderExchange::class)
                ->constrained()
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->enum('status', ['active','inactive'])->default('active');
            $table->decimal('min', 8,3)->default(0);
            $table->decimal('max', 8,3)->default(1000);
            $table->enum('type',['tomorrow','day_after_tomorrow','coins'])->default('tomorrow');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('selected_auto_order_exchanges');
    }
};
