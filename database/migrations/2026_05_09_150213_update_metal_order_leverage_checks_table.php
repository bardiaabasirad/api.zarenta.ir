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
        Schema::table('metal_order_leverage_checks', function (Blueprint $table) {
            $table->dropColumn('shortage_amount');
            $table->decimal('max_acceptable_order', 15, 0)->after('order_value')->nullable();
        });

        DB::statement("
            ALTER TABLE metal_order_leverage_checks
            MODIFY COLUMN status ENUM(
                'pending',
                'sufficient',
                'insufficient',
                'connection_failed',
                'missing_accounting_id'
            ) NOT NULL DEFAULT 'pending'
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("
            ALTER TABLE metal_order_leverage_checks
            MODIFY COLUMN status ENUM(
                'pending',
                'sufficient',
                'insufficient',
                'connection_failed'
            ) NOT NULL DEFAULT 'pending'
        ");

        Schema::table('metal_order_leverage_checks', function (Blueprint $table) {
            $table->decimal('shortage_amount', 15, 0)->after('order_value')->nullable();
            $table->dropColumn('max_acceptable_order');
        });
    }
};
