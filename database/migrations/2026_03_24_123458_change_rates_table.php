<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // حذف FK و ایندکس قبل از rename
        Schema::table('rates', function (Blueprint $table) {
            $table->dropForeign('rates_reference_channel_id_foreign');
            $table->dropIndex('idx_rates_type_created');
        });

        // تغییر نام جدول
        Schema::rename('rates', 'selected_metal_prices');

        // تغییرات ستون‌ها
        Schema::table('selected_metal_prices', function (Blueprint $table) {
            $table->dropColumn(['type', 'ref_type']);

            $table->renameColumn('reference_channel_id', 'price_source_id');

            $table->foreignId('metal_item_id')
                ->after('price_source_id')
                ->constrained()
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->foreign('price_source_id')
                ->references('id')
                ->on('price_sources')
                ->cascadeOnUpdate()
                ->nullOnDelete();
        });

        Schema::table('selected_metal_prices', function (Blueprint $table) {
            $table->index(['metal_item_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::table('selected_metal_prices', function (Blueprint $table) {
            $table->dropIndex(['metal_item_id', 'id']);
        });

        Schema::table('selected_metal_prices', function (Blueprint $table) {
            $table->dropForeign(['metal_item_id']);
            $table->dropForeign(['price_source_id']);
            $table->dropColumn('metal_item_id');

            $table->renameColumn('price_source_id', 'reference_channel_id');

            $table->enum('type', [
                'tomorrow','day_after_tomorrow','gold_coin_86','gold_half_coin_86',
                'gold_quarter_coin_86','gold_coin_old_version','gold_coin_half_old_version',
                'gold_coin_quarter_old_version','gold_half_coin_403','gold_quarter_coin_403',
                'gold_coin_404','gold_half_coin_404','gold_quarter_coin_404'
            ])->after('reference_channel_id');

            $table->enum('ref_type', ['telegram','taban','custom'])
                ->default('telegram')
                ->after('type');
        });

        Schema::table('selected_metal_prices', function (Blueprint $table) {
            $table->foreign('reference_channel_id')
                ->references('id')
                ->on('reference_channels')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->index(['type', 'created_at'], 'idx_rates_type_created');
        });

        Schema::rename('selected_metal_prices', 'rates');
    }
};
