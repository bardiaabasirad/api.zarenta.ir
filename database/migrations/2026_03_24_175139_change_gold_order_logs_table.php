<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // حذف FK قبل از rename
        Schema::table('gold_order_logs', function (Blueprint $table) {
            $table->dropForeign('gold_order_logs_gold_order_id_foreign');
        });

        // تغییر نام جدول
        Schema::rename('gold_order_logs', 'metal_order_logs');

        // تغییرات ستون
        Schema::table('metal_order_logs', function (Blueprint $table) {
            $table->renameColumn('gold_order_id', 'metal_order_id');

            $table->foreign('metal_order_id')
                ->references('id')
                ->on('metal_orders')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('metal_order_logs', function (Blueprint $table) {
            $table->dropForeign(['metal_order_id']);

            $table->renameColumn('metal_order_id', 'gold_order_id');

            $table->foreign('gold_order_id')
                ->references('id')
                ->on('gold_orders')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
        });

        Schema::rename('metal_order_logs', 'gold_order_logs');
    }
};
