<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('metal_orders', function (Blueprint $table) {
            $table->string('source_order_id', 64)
                ->nullable()
                ->after('tracking_code');

            $table->string('created_type', 32)->change();

            $table->unique(
                ['source_order_id', 'created_type', 'created_id'],
                'metal_orders_source_created_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('metal_orders', function (Blueprint $table) {
            $table->dropUnique('metal_orders_source_created_unique');

            $table->string('created_type', 255)->change();

            $table->dropColumn('source_order_id');
        });
    }
};

