<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('metal_physical_delivery_requests', function (Blueprint $table) {
            $table->decimal('delivered_qty', 20, 3)->nullable()->after('qty')
                ->comment('وزن/تعداد واقعاً تحویل‌شده؛ فقط پس از DELIVERED');
            $table->decimal('fee_adjustment_amount', 20, 0)->nullable()->after('fee_amount')
                ->comment('تعدیل کارمزد نسبت به fee_amount؛ مثبت=کسر اضافه، منفی=استرداد');
            $table->unsignedBigInteger('fee_adjustment_wallet_tx_id')->nullable()->after('fee_refund_wallet_tx_id');
        });
    }

    public function down(): void
    {
        Schema::table('metal_physical_delivery_requests', function (Blueprint $table) {
            $table->dropColumn([
                'delivered_qty',
                'fee_adjustment_amount',
                'fee_adjustment_wallet_tx_id',
            ]);
        });
    }
};
