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
        Schema::rename('gold_order_rejection_reasons', 'metal_order_rejection_reasons');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::rename('metal_order_rejection_reasons', 'gold_order_rejection_reasons');
    }
};
