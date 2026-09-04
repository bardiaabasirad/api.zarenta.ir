<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('selected_metal_prices', function (Blueprint $table) {
            // Index اصلی برای HamtalaPriceService - getLatestProductsQuery
            // WHERE price_source_id = 5 AND id IN (...)
            $table->index(
                ['price_source_id', 'metal_item_id', 'id'],
                'idx_source_item_id'
            );

            // Index برای getProductPrice و مرتب‌سازی
            // WHERE price_source_id = 5 AND metal_item_id = X ORDER BY time DESC, id DESC
            $table->index(
                ['price_source_id', 'metal_item_id', 'time', 'id'],
                'idx_source_item_time_id'
            );

            // Index برای getLastUpdatedAt
            // WHERE price_source_id = 5 ORDER BY updated_at DESC
            $table->index(
                ['price_source_id', 'updated_at'],
                'idx_source_updated'
            );
        });
    }

    public function down(): void
    {
        Schema::table('selected_metal_prices', function (Blueprint $table) {
            $table->dropIndex('idx_source_item_id');
            $table->dropIndex('idx_source_item_time_id');
            $table->dropIndex('idx_source_updated');
        });
    }
};
