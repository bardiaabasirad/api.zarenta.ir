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
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->comment('دفتر کل تراکنش‌های کیف پول');

            $table->foreignIdFor(\App\Models\MetalTraderWallet::class)
                ->constrained()
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            $table->foreignIdFor(\App\Models\MetalOrder::class)
                ->nullable()
                ->constrained()
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            $table->decimal('amount', 20, 3)->comment('مبلغ تراکنش (همواره مثبت)');

            $table->enum('type', [
                'deposit',           // واریز نقد یا حواله بانکی به کیف پول
                'withdraw',          // برداشت وجه یا ارسال حواله به حساب کاربر
                'block_collateral',  // انتقال از available به blocked
                'unblock_collateral',// بازگشت از blocked به available
                'order_settlement',  // کسر یا اضافه شدن بابت تسویه قطعی سفارش
                'penalty_loss'       // کسر جریمه نکول یا زیان معامله معکوس
            ]);

            // دو فیلد حیاتی برای حسابرسی و دیباگ مالی:
            $table->decimal('balance_before', 20, 3)->default(0)->comment('مانده موجودی قبل از تراکنش');
            $table->decimal('balance_after', 20, 3)->default(0)->comment('مانده موجودی بعد از تراکنش');

            // اطلاعات تکمیلی برای سناریوهای پرداخت/دریافت حواله که گفتی:
            $table->string('reference_number')->nullable()->comment('شماره پیگیری فیش بانکی یا کد حواله ساتنا/پایا');
            $table->text('description')->nullable()->comment('توضیحات مدیر یا سیستم');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
    }
};
