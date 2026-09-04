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
        Schema::table('metal_item_price_source', function (Blueprint $table) {
            // تغییر نام ستون موجود برای صراحت بیشتر
            $table->renameColumn('external_identifier', 'rate_external_identifier');
        });

        Schema::table('metal_item_price_source', function (Blueprint $table) {
            // افزودن ستون جدید برای نگاشت سفارش
            $table->string('order_external_identifier', 191)
                ->nullable()
                ->after('rate_external_identifier');

            // اطمینان از nullable بودن ستون نرخ (در صورت نیاز)
            $table->string('rate_external_identifier', 191)
                ->nullable()
                ->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('metal_item_price_source', function (Blueprint $table) {
            $table->dropColumn('order_external_identifier');
        });

        Schema::table('metal_item_price_source', function (Blueprint $table) {
            $table->renameColumn('rate_external_identifier', 'external_identifier');
        });
    }
};
