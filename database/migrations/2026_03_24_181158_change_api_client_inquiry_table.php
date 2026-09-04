<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // حذف FK قبل از rename
        Schema::table('api_client_inquiry', function (Blueprint $table) {
            $table->dropForeign('api_client_inquiry_api_client_id_foreign');
            $table->dropColumn('api_client_id');
        });

        Schema::rename('api_client_inquiry', 'inquiry_metal_trader');

        Schema::table('metal_trader_inquiry', function (Blueprint $table) {
            // اول nullable اضافه کن تا با رکوردهای موجود تضاد نداشته باشد
            $table->unsignedBigInteger('metal_trader_id')->nullable()->first();
        });

        // FK جداگانه بعد از اضافه شدن ستون
        Schema::table('metal_trader_inquiry', function (Blueprint $table) {
            $table->foreign('metal_trader_id')
                ->references('id')
                ->on('metal_traders')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::table('metal_trader_inquiry', function (Blueprint $table) {
            $table->dropForeign(['metal_trader_id']);
            $table->dropColumn('metal_trader_id');
        });

        Schema::rename('inquiry_metal_trader', 'api_client_inquiry');

        Schema::table('api_client_inquiry', function (Blueprint $table) {
            $table->unsignedBigInteger('api_client_id')->nullable()->first();
            $table->foreign('api_client_id')
                ->references('id')
                ->on('api_clients')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
        });
    }
};
