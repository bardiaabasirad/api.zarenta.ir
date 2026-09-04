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
            DB::table('metal_orders')
                ->where('created_type', 'App\\Models\\ApiClient')
                ->update(['created_type' => 'App\\Models\\MetalTrader']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('metal_orders', function (Blueprint $table) {
            DB::table('metal_orders')
                ->where('created_type', 'App\\Models\\MetalTrader')
                ->update(['created_type' => 'App\\Models\\ApiClient']);
        });
    }
};
