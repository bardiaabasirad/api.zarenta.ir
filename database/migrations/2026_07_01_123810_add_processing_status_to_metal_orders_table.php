<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('metal_orders', function (Blueprint $table) {
            $table->enum('status', ['pending', 'processing', 'succeed', 'rejected'])
                ->change();
        });
    }

    public function down(): void
    {
        Schema::table('metal_orders', function (Blueprint $table) {
            $table->enum('status', ['pending', 'succeed', 'rejected'])
                ->change();
        });
    }
};
