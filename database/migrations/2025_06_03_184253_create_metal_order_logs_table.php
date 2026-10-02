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
        Schema::create('metal_order_logs', function (Blueprint $table) {
            $table->id();

            // ارتباط مستقیم با سفارشات فلزات (Metal Orders)
            $table->foreignId('metal_order_id')
                ->constrained('metal_orders')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            // ستون‌های پلی‌مورفیک برای عامل تغییر (User، Admin یا System)
            $table->nullableMorphs('loggable');

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
        Schema::dropIfExists('metal_order_logs');
    }
};
