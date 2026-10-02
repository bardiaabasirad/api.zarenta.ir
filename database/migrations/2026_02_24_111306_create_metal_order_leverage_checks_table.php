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
        Schema::create('metal_order_leverage_checks', function (Blueprint $table) {
            $table->id();

            $table->foreignId('metal_order_id')
                ->unique()
                ->constrained('metal_orders')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->enum('status', [
                'pending',
                'sufficient',
                'insufficient',
                'connection_failed',
                'missing_accounting_id',
            ])->default('pending');

            $table->unsignedTinyInteger('applied_leverage');

            // سنجش درصدی حجم سفارش و موجودی خالص تومانی
            $table->decimal('order_percentage', 8, 2)->nullable();
            $table->decimal('net_toman_balance', 14, 0)->nullable();

            $table->decimal('order_value', 12, 0)->default(0);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('metal_order_leverage_checks');
    }
};
