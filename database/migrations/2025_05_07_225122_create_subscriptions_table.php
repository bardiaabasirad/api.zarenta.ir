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
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('metal_trader_id')
                ->constrained('metal_traders')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->dateTime('starts_at')->nullable(); // تاریخ شروع اشتراک
            $table->dateTime('ends_at')->nullable(); // تاریخ انقضای اشتراک
            $table->decimal('amount', 10, 0)->default(0); // مبلغ اشتراک (برای ثبت قیمت در زمان خرید)

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
