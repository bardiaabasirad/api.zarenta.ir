<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('metal_orders')
            ->where('created_type', 'App\\Models\\MetalTrader')
            ->update(['created_type' => 'metal_trader']);

        DB::table('metal_orders')
            ->where('created_type', 'App\\Models\\Order')
            ->update(['created_type' => 'order']);

        DB::table('metal_orders')
            ->where('created_type', 'App\\Models\\Admin')
            ->update(['created_type' => 'admin']);
    }

    public function down(): void
    {
        DB::table('metal_orders')
            ->where('created_type', 'metal_trader')
            ->update(['created_type' => 'App\\Models\\MetalTrader']);

        DB::table('metal_orders')
            ->where('created_type', 'order')
            ->update(['created_type' => 'App\\Models\\Order']);

        DB::table('metal_orders')
            ->where('created_type', 'admin')
            ->update(['created_type' => 'App\\Models\\Admin']);
    }
};
