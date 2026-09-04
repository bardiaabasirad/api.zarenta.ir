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
        Schema::table('metal_traders', function (Blueprint $table) {
            DB::statement("ALTER TABLE metal_traders MODIFY COLUMN status ENUM('pre_registered', 'pending', 'active', 'rejected', 'inactive') NOT NULL DEFAULT 'pre_registered'");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('metal_traders', function (Blueprint $table) {
            DB::statement("ALTER TABLE metal_traders MODIFY COLUMN status ENUM('pre_registered', 'pending', 'active', 'inactive') NOT NULL DEFAULT 'pre_registered'");
        });
    }
};
