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
        // تغییر نام جدول
        Schema::rename('api_clients', 'metal_traders');

        // حذف ستون products_settings
        Schema::table('metal_traders', function (Blueprint $table) {
            $table->dropColumn('products_settings');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // بازگرداندن ستون products_settings
        Schema::table('metal_traders', function (Blueprint $table) {
            $table->json('products_settings')->nullable()->after('trade_leverage');
        });

        // بازگرداندن نام جدول
        Schema::rename('metal_traders', 'api_clients');
    }
};
