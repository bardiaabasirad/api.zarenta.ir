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
        Schema::table('metal_order_exchanges', function (Blueprint $table) {
            $table->json('details')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('metal_order_exchanges', function (Blueprint $table) {
            $table->string('details', 64)
                ->nullable()
                ->comment('Secondary reason code such as rate_changed, timeout, product_not_found')
                ->change();
        });
    }
};
