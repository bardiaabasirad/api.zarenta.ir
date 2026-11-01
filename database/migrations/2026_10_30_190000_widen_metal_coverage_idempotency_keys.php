<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE `metal_treasury_movements` MODIFY `idempotency_key` VARCHAR(128) NOT NULL');
        DB::statement('ALTER TABLE `metal_order_coverage_attempts` MODIFY `idempotency_key` VARCHAR(128) NOT NULL');
        DB::statement('ALTER TABLE `metal_order_coverage_allocations` MODIFY `idempotency_key` VARCHAR(128) NOT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE `metal_treasury_movements` MODIFY `idempotency_key` VARCHAR(64) NOT NULL');
        DB::statement('ALTER TABLE `metal_order_coverage_attempts` MODIFY `idempotency_key` VARCHAR(64) NOT NULL');
        DB::statement('ALTER TABLE `metal_order_coverage_allocations` MODIFY `idempotency_key` VARCHAR(64) NOT NULL');
    }
};
