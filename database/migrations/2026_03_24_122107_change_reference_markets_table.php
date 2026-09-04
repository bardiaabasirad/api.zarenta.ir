<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // حذف FK قبل از rename جدول
        Schema::table('reference_markets', function (Blueprint $table) {
            $table->dropForeign('reference_markets_gold_dealer_id_foreign');
        });

        // تغییر نام جدول
        Schema::rename('reference_markets', 'price_source_mappings');

        // rename ستون‌ها
        Schema::table('price_source_mappings', function (Blueprint $table) {
            $table->renameColumn('reference_channel_id', 'price_source_id');
            $table->renameColumn('type', 'metal_item_id');
        });

        // تغییر نوع و اضافه کردن FKهای جدید
        Schema::table('price_source_mappings', function (Blueprint $table) {
            $table->unsignedBigInteger('metal_item_id')->unique()->change();

            $table->foreign('price_source_id')
                ->references('id')
                ->on('price_sources')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->foreign('metal_item_id')
                ->references('id')
                ->on('metal_items')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('price_source_mappings', function (Blueprint $table) {
            $table->dropForeign(['metal_item_id']);
            $table->dropForeign(['price_source_id']);
        });

        Schema::table('price_source_mappings', function (Blueprint $table) {
            $table->enum('metal_item_id', [
                'tomorrow',
                'day_after_tomorrow',
                'gold_coin_86',
                'gold_half_coin_86',
                'gold_quarter_coin_86',
                'gold_coin_old_version'
            ])->change();

            $table->renameColumn('metal_item_id', 'type');
            $table->renameColumn('price_source_id', 'reference_channel_id');
        });

        Schema::table('price_source_mappings', function (Blueprint $table) {
            $table->foreign('reference_channel_id')
                ->references('id')
                ->on('reference_channels')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
        });

        Schema::rename('price_source_mappings', 'reference_markets');
    }
};
