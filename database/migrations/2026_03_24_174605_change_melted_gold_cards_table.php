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
        Schema::rename('melted_gold_cards', 'metal_cards');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::rename('metal_cards', 'melted_gold_cards');
    }
};
