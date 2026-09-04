<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Disable FK checks (MySQL)
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        DB::table('tel_market_prices')->truncate();
        DB::table('rates')->truncate();
        DB::table('reference_markets')->truncate();
        DB::table('api_client_inquiry')->truncate();
        DB::table('reference_channels')->truncate();

        // Enable FK checks
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        logger()->warning('Down method for truncate migration was called but no reverse action is possible.');
    }
};
