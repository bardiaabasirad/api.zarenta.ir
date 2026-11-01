<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('metal_trader_fiat_withdrawal_rejection_reasons', function (Blueprint $table) {
            $table->id();
            $table->string('value');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('metal_trader_fiat_withdrawals', function (Blueprint $table) {
            $table->unsignedBigInteger('rejection_reason_id')->nullable()->after('reject_reason');

            $table->foreign('rejection_reason_id', 'mtfw_reject_reason_fk')
                ->references('id')
                ->on('metal_trader_fiat_withdrawal_rejection_reasons')
                ->cascadeOnUpdate()
                ->nullOnDelete();
        });

        DB::table('metal_trader_fiat_withdrawal_rejection_reasons')->insert([
            [
                'value' => 'اطلاعات حساب بانکی با مشخصات کاربر مطابقت ندارد',
                'sort_order' => 1,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'value' => 'حساب بانکی مقصد قادر به دریافت وجه نیست',
                'sort_order' => 2,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'value' => 'درخواست تکراری یا مشکوک است',
                'sort_order' => 3,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'value' => 'مدارک هویتی ناقص است',
                'sort_order' => 4,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'value' => 'سقف برداشت روزانه/ماهانه تکمیل شده است',
                'sort_order' => 5,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'value' => 'سایر',
                'sort_order' => 6,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::table('metal_trader_fiat_withdrawals', function (Blueprint $table) {
            $table->dropForeign('mtfw_reject_reason_fk');
            $table->dropColumn('rejection_reason_id');
        });

        Schema::dropIfExists('metal_trader_fiat_withdrawal_rejection_reasons');
    }
};
