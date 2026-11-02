<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE metal_order_coverage_allocations MODIFY COLUMN source_type ENUM(
            'TREASURY',
            'PARTNER',
            'USER_WALLET_SELL',
            'PHYSICAL_PURCHASE',
            'TRADITIONAL_EXIT'
        ) NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE metal_order_coverage_allocations MODIFY COLUMN source_type ENUM(
            'TREASURY',
            'PARTNER',
            'USER_WALLET_SELL',
            'PHYSICAL_PURCHASE'
        ) NOT NULL");
    }
};
