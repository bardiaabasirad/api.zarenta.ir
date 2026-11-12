<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('metal_traders', 'business_type')) {
            return;
        }

        DB::statement("ALTER TABLE metal_traders MODIFY business_type ENUM('gold_dealer','trader','other') NULL DEFAULT NULL");

        // Clear default business_type for incomplete registrations so the step can be detected.
        DB::table('metal_traders')
            ->where('status', 'pre_registered')
            ->where(function ($query) {
                $query->whereNull('name')
                    ->orWhere('name', '')
                    ->orWhereNull('national_code')
                    ->orWhere('national_code', '')
                    ->orWhereNull('address')
                    ->orWhere('address', '');
            })
            ->update(['business_type' => null]);
    }

    public function down(): void
    {
        if (! Schema::hasColumn('metal_traders', 'business_type')) {
            return;
        }

        DB::table('metal_traders')
            ->whereNull('business_type')
            ->update(['business_type' => 'gold_dealer']);

        DB::statement("ALTER TABLE metal_traders MODIFY business_type ENUM('gold_dealer','trader','other') NOT NULL DEFAULT 'gold_dealer'");
    }
};
