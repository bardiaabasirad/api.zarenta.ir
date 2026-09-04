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
        Schema::create('gold_order_exchanges', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(\App\Models\GoldOrder::class)
                ->constrained()
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreignIdFor(\App\Models\ReferenceChannel::class)
                ->constrained()
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->enum('status', [
                'pending',     // منتظر پردازش Puppeteer
                'processing',     // در حال پردازش سفارش
                'placed',     // سفارش در صرافی ثبت شد
                'confirmed',   // توسط صرافی تایید شده
                'rejected',    // توسط صرافی رد شده
                'error'        // خطایی در بررسی یا ارتباط
            ])->default('pending')->index();

            $table->decimal('rate', 10,0)->nullable();

            // 🔹 جزئیات ثانویه (کد انگلیسی خطا یا دلیل رد شدن)
            $table->string('details', 64)->nullable()->comment('Secondary reason code such as rate_changed, timeout, product_not_found');

            // 🔹 توضیح یا پیام کاربرپسند — برای نمایش در UI / گزارشات
            $table->string('message', 255)->nullable();

            // 🔹 شمارنده تلاش مجدد
            $table->tinyInteger('retry_count')->default(0);

            // 🔹 تاریخ‌ و زمان‌های کلیدی
            $table->timestamp('sent_at')->nullable()->comment('زمان ارسال سفارش به صرافی');
            $table->timestamp('placed_at')->nullable()->comment('زمان ثبت نهایی سفارش در صرافی');
            $table->timestamp('resolved_at')->nullable()->comment('زمان مشخص شدن نتیجه سفارش در صرافی');

            // 🔹 ایندکس ترکیبی برای جست‌وجو سریع‌تر
            $table->index(['status', 'retry_count']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gold_order_exchanges');
    }
};
