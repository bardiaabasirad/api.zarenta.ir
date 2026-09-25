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
        Schema::create('metal_order_collaterals', function (Blueprint $table) {
            $table->id();
            $table->comment('جدول نگهداری جزئیات ضمانت‌های بلوکه‌شده به تفکیک کیف پول');
            $table->foreignIdFor(\App\Models\MetalTrader::class)
                ->constrained()
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->foreignIdFor(\App\Models\MetalTraderWallet::class)
                ->constrained()
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->decimal('amount_blocked', 20,3)
                ->nullable()
                ->comment('مقدار دارایی بلوکه‌شده (مثلاً 2.000 گرم یا 46000000 تومان)');
            $table->decimal('equivalent_irr_value', 20,0)
                ->nullable()
                ->comment('ارزش معادل ریالی مقدار بلوکه‌شده در لحظه دقیق ثبت سفارش');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('metal_order_collaterals');
    }
};
