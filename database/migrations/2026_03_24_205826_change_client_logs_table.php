<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // حذف FK قبل از rename
        Schema::table('client_logs', function (Blueprint $table) {
            $table->dropForeign('client_logs_api_client_id_foreign');
        });

        // تغییر نام جدول
        Schema::rename('client_logs', 'metal_trader_logs');

        // تغییرات ستون
        Schema::table('metal_trader_logs', function (Blueprint $table) {
            $table->renameColumn('api_client_id', 'metal_trader_id');

            $table->foreign('metal_trader_id')
                ->references('id')
                ->on('metal_traders')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('metal_trader_logs', function (Blueprint $table) {
            $table->dropForeign(['metal_trader_id']);
            $table->renameColumn('metal_trader_id', 'api_client_id');

            $table->foreign('api_client_id')
                ->references('id')
                ->on('api_clients')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
        });

        Schema::rename('metal_trader_logs', 'client_logs');
    }
};
