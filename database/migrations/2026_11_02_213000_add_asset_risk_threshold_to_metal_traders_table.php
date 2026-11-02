<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('metal_traders', function (Blueprint $table) {
            $table->decimal('asset_risk_threshold', 5, 2)
                ->default(10)
                ->after('trade_leverage')
                ->comment('حداقل نسبت مجاز ارزش دارایی‌ها به مبلغ سفارش (درصد)');
        });

        if (Schema::hasTable('metal_order_rejection_reasons')) {
            $exists = DB::table('metal_order_rejection_reasons')
                ->where('value', 'کمبود نسبت دارایی به مبلغ سفارش')
                ->exists();

            if (!$exists) {
                DB::table('metal_order_rejection_reasons')->insert([
                    'value' => 'کمبود نسبت دارایی به مبلغ سفارش',
                    'retry' => 'inactive',
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('metal_traders', function (Blueprint $table) {
            $table->dropColumn('asset_risk_threshold');
        });

        if (Schema::hasTable('metal_order_rejection_reasons')) {
            DB::table('metal_order_rejection_reasons')
                ->where('value', 'کمبود نسبت دارایی به مبلغ سفارش')
                ->delete();
        }
    }
};
