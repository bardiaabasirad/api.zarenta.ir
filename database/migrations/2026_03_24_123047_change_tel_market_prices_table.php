<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // حذف FK و ایندکس‌ها قبل از rename
        Schema::table('tel_market_prices', function (Blueprint $table) {
            $table->dropForeign('tel_market_prices_reference_channel_id_foreign');
            $table->dropIndex('type_created_at_index');
            $table->dropIndex('idx_tel_market_prices_lookup');
        });

        // تغییر نام جدول
        Schema::rename('tel_market_prices', 'raw_metal_prices');

        // تغییرات ستون‌ها
        Schema::table('raw_metal_prices', function (Blueprint $table) {
            $table->renameColumn('reference_channel_id', 'price_source_id');
            $table->renameColumn('type', 'metal_item_id');

            $table->unsignedBigInteger('metal_item_id')->change();

            $table->foreign('price_source_id')
                ->references('id')
                ->on('price_sources')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            $table->foreign('metal_item_id')
                ->references('id')
                ->on('metal_items')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::table('raw_metal_prices', function (Blueprint $table) {
            $table->dropForeign(['metal_item_id']);
            $table->dropForeign(['price_source_id']);

            $table->enum('metal_item_id', [
                'tomorrow','day_after_tomorrow','gold_coin_86','gold_half_coin_86',
                'gold_quarter_coin_86','gold_coin_old_version','gold_coin_half_old_version',
                'gold_coin_quarter_old_version','gold_half_coin_403','gold_quarter_coin_403',
                'gold_coin_404','gold_half_coin_404','gold_quarter_coin_404'
            ])->change();

            $table->renameColumn('metal_item_id', 'type');
            $table->renameColumn('price_source_id', 'reference_channel_id');
        });

        Schema::table('raw_metal_prices', function (Blueprint $table) {
            $table->foreign('reference_channel_id')
                ->references('id')
                ->on('reference_channels')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            $table->index(['type', 'created_at'], 'type_created_at_index');
            $table->index(['type', 'reference_channel_id', 'created_at'], 'idx_tel_market_prices_lookup');
        });

        Schema::rename('raw_metal_prices', 'tel_market_prices');
    }
};
