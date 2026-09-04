<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // حذف FK ها قبل از rename
        Schema::table('gold_order_exchanges', function (Blueprint $table) {
            $table->dropForeign('gold_order_exchanges_gold_order_id_foreign');
            $table->dropForeign('gold_order_exchanges_reference_channel_id_foreign');
        });

        // تغییر نام جدول
        Schema::rename('gold_order_exchanges', 'metal_order_exchanges');

        // تغییرات ستون‌ها
        Schema::table('metal_order_exchanges', function (Blueprint $table) {
            $table->renameColumn('gold_order_id', 'metal_order_id');
            $table->renameColumn('reference_channel_id', 'price_source_id');

            $table->foreign('metal_order_id')
                ->references('id')
                ->on('metal_orders')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->foreign('price_source_id')
                ->references('id')
                ->on('price_sources')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('metal_order_exchanges', function (Blueprint $table) {
            $table->dropForeign(['metal_order_id']);
            $table->dropForeign(['price_source_id']);

            $table->renameColumn('metal_order_id', 'gold_order_id');
            $table->renameColumn('price_source_id', 'reference_channel_id');

            $table->foreign('gold_order_id')
                ->references('id')
                ->on('gold_orders')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->foreign('reference_channel_id')
                ->references('id')
                ->on('reference_channels')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
        });

        Schema::rename('metal_order_exchanges', 'gold_order_exchanges');
    }
};
