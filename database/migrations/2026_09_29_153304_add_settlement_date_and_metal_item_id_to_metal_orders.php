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
        Schema::table('metal_orders', function (Blueprint $table) {
            $table->date('settlement_date')
                ->nullable()
                ->after('extra_data')
                ->index('idx_metal_orders_settlement_date');

            $table->enum('settlement_status', ['pending', 'settled'])
                ->default('settled')
                ->after('settlement_date')
                ->index();

            $table->foreignIdFor(\App\Models\MetalItem::class)
                ->after('source_order_id')
                ->nullable()
                ->constrained()
                ->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('metal_orders', function (Blueprint $table) {
            // ابتدا کلید خارجی و ستون metal_item_id
            $table->dropForeign(['metal_item_id']);
            $table->dropColumn('metal_item_id');

            // سپس ایندکس صریح و ستون settlement_date
            $table->dropIndex('idx_metal_orders_settlement_date');
            $table->dropColumn('settlement_date');

            $table->dropColumn('settlement_status');
        });
    }
};
