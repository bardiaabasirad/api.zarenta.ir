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
        Schema::table('metal_item_price_source', function (Blueprint $table) {
            $table->renameColumn('name', 'external_identifier');
        });

        Schema::table('metal_item_price_source', function (Blueprint $table) {
            $table->index(
                ['price_source_id', 'external_identifier'],
                'idx_source_external_identifier'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('metal_item_price_source', function (Blueprint $table) {
            $table->dropIndex('idx_source_external_identifier');
            $table->renameColumn('external_identifier', 'name');
        });
    }
};
