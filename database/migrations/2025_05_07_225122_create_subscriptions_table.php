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
            $table->foreignIdFor(\App\Models\ApiClient::class)
                ->constrained()
                ->cascadeOnUpdate()
                ->cascadeOnUpdate();
            $table->timestamp('starts_at'); // تاریخ شروع اشتراک
            $table->timestamp('ends_at'); // تاریخ انقضای اشتراک
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
