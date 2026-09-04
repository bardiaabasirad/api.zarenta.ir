<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // حذف FK و unique index قبل از rename
        Schema::table('order_leverage_checks', function (Blueprint $table) {
            $table->dropForeign('order_leverage_checks_gold_order_id_foreign');
            $table->dropUnique('order_leverage_checks_gold_order_id_unique');
        });

        // تغییر نام جدول
        Schema::rename('order_leverage_checks', 'metal_order_leverage_checks');

        // تغییرات ستون
        Schema::table('metal_order_leverage_checks', function (Blueprint $table) {
            $table->renameColumn('gold_order_id', 'metal_order_id');

            $table->unique('metal_order_id');

            $table->foreign('metal_order_id')
                ->references('id')
                ->on('metal_orders')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('metal_order_leverage_checks', function (Blueprint $table) {
            $table->dropForeign(['metal_order_id']);
            $table->dropUnique(['metal_order_id']);

            $table->renameColumn('metal_order_id', 'gold_order_id');

            $table->unique('gold_order_id');

            $table->foreign('gold_order_id')
                ->references('id')
                ->on('gold_orders')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
        });

        Schema::rename('metal_order_leverage_checks', 'order_leverage_checks');
    }
};
