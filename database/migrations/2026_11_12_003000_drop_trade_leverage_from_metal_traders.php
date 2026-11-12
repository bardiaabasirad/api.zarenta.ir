<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('metal_traders', function (Blueprint $table) {
            if (Schema::hasColumn('metal_traders', 'trade_leverage')) {
                $table->dropColumn('trade_leverage');
            }
        });
    }

    public function down(): void
    {
        Schema::table('metal_traders', function (Blueprint $table) {
            if (! Schema::hasColumn('metal_traders', 'trade_leverage')) {
                $table->unsignedTinyInteger('trade_leverage')->nullable();
            }
        });
    }
};
