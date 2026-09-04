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
        Schema::table('dealing_groups', function (Blueprint $table) {
            $table->dropColumn('products_settings');
            $table->dropColumn('tolerance_type');
            $table->dropColumn('display_mode');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dealing_groups', function (Blueprint $table) {
            $table->json('products_settings')->nullable()->after('id');
            $table->enum('tolerance_type', ['fixed_amount','percentage'])->default('fixed_amount');
            $table->enum('display_mode', ['quotation','per_gram'])->default('quotation');
        });
    }
};
