<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('metal_item_groups', function (Blueprint $table) {
            $table->string('icon', 32)->nullable()->after('title');
        });

        Schema::table('metal_items', function (Blueprint $table) {
            $table->boolean('allows_physical_delivery')->default(false)->after('is_spot');
        });

        DB::table('metal_items')
            ->where('is_spot', true)
            ->update(['allows_physical_delivery' => true]);
    }

    public function down(): void
    {
        Schema::table('metal_item_groups', function (Blueprint $table) {
            $table->dropColumn('icon');
        });

        Schema::table('metal_items', function (Blueprint $table) {
            $table->dropColumn('allows_physical_delivery');
        });
    }
};
