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
        Schema::create('metal_trader_wallets', function (Blueprint $table) {
            $table->id();
            $table->comment('جدول کیف پول کاربران به تفکیک فلزات');

            $table->foreignIdFor(\App\Models\MetalTrader::class)
                ->constrained()
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            $table->foreignIdFor(\App\Models\MetalItem::class)
                ->nullable()
                ->comment('اگر نال باشد یعنی کیف پول تومانی کاربر است')
                ->constrained()
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            $table->decimal('available_balance', 20,3)->nullable();
            $table->decimal('blocked_balance', 20,3)->nullable();

            $table->decimal('fiat_balance', 20, 0)
                ->default(0)
                ->comment('موجودی تومانی در دسترس برای تسویه در سررسید');

            $table->decimal('avg_buy_price', 20, 0)->unsigned()->default(0);

            $table->timestamps();

            $table->unique(['metal_trader_id', 'metal_item_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('metal_trader_wallets');
    }
};
