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
        Schema::table('metal_order_leverage_checks', function (Blueprint $table) {
            // حذف ستون‌های موجود
            $table->dropColumn([
                'user_balance_at_check',
                'max_acceptable_order'
            ]);

            // افزودن ستون جدید
            // با دقت 8 رقم در کل و 2 رقم اعشار (می‌توانی بر اساس نیاز تغییرش دهی)
            $table->decimal('order_percentage', 8, 2)->after('applied_leverage')->nullable();
            $table->decimal('net_toman_balance', 14, 0)->after('order_percentage')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('metal_order_leverage_checks', function (Blueprint $table) {
            $table->dropColumn(['order_percentage', 'net_toman_balance']);

            // بازگرداندن ستون‌های حذف شده در صورت نیاز به Rollback
            $table->decimal('user_balance_at_check', 15, 2)->after('applied_leverage')->nullable();
            $table->decimal('max_acceptable_order', 15, 2)->after('user_balance_at_check')->nullable();
        });
    }
};
