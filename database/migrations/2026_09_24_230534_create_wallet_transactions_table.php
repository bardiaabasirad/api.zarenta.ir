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

            $table->foreignIdFor(\App\Models\WalletTransaction::class, 'related_transaction_id')
                ->nullable()
                ->comment('شناسه تراکنش متناظر در انتقالات بین کیف‌پول‌ها')
                ->constrained('wallet_transactions')
                ->nullOnDelete();

            $table->decimal('amount', 20, 3)->comment('مبلغ تراکنش (همواره مثبت)');

            $table->enum('type', [
                // عملیات بانکی و پایه (کیف‌پول حاضر)
                'deposit',
                'withdraw',
                'block_collateral',
                'unblock_collateral',

                // تسویه‌ها و تعهدات وزنی فلز (بر حسب گرم)
                'metal_commitment_in',     // یا trade_metal_in: ورود گرمی تعهد به کیف فردایی
                'metal_commitment_out',    // یا trade_metal_out: خروج گرمی تعهد از کیف فردایی
                'settlement_metal_in',     // ورود قطعی فلز در زمان سررسید به کیف حاضر
                'settlement_metal_out',    // خروج فلز از کیف فردایی جهت بستن قرارداد

                // تسویه‌ها و تعهدات پولی / فیات (بر حسب تومان)
                'fiat_debt_in',            // ثبت بدهی تومانی (کاهش fiat_balance)
                'fiat_credit_in',          // ثبت طلب یا سود تومانی (افزایش fiat_balance)
                'settlement_fiat_transfer',// انتقال خالص سود/زیان تومانی به کیف‌پول حاضر

                // جرایم یا ضررهای تسویه
                'penalty_loss',
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
