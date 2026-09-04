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
        Schema::table('metal_trader_leads', function (Blueprint $table) {
            $table->enum('lead_type', ['signup_abandoned', 'consultation_request'])
                ->default('signup_abandoned')
                ->after('phone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('metal_trader_leads', function (Blueprint $table) {
            $table->dropColumn('lead_type');
        });
    }
};
