<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('raw_metal_prices', function (Blueprint $table) {
            $table->index(['metal_item_id', 'time'], 'idx_raw_metal_item_time');
        });

        Schema::table('selected_metal_prices', function (Blueprint $table) {
            $table->index(['metal_item_id', 'time'], 'idx_selected_metal_item_time');
        });
    }

    public function down(): void
    {
        Schema::table('raw_metal_prices', function (Blueprint $table) {
            $table->dropIndex('idx_raw_metal_item_time');
        });

        Schema::table('selected_metal_prices', function (Blueprint $table) {
            $table->dropIndex('idx_selected_metal_item_time');
        });
    }
};
