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
        Schema::table('metal_item_groups', function (Blueprint $table) {
            $table->integer('sort_order')->default(0)->after('id');
            $table->index('sort_order');
        });

        DB::statement('UPDATE metal_items SET sort_order = id');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('metal_item_groups', function (Blueprint $table) {
            $table->dropIndex(['sort_order']);
            $table->dropColumn('sort_order');
        });
    }
};
