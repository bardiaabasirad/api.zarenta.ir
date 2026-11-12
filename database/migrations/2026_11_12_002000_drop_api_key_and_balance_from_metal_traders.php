<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('metal_traders', function (Blueprint $table) {
            if (Schema::hasColumn('metal_traders', 'api_key')) {
                $table->dropUnique(['api_key']);
                $table->dropColumn('api_key');
            }

            if (Schema::hasColumn('metal_traders', 'balance')) {
                $table->dropColumn('balance');
            }
        });
    }

    public function down(): void
    {
        Schema::table('metal_traders', function (Blueprint $table) {
            if (! Schema::hasColumn('metal_traders', 'api_key')) {
                $table->string('api_key', 32)->nullable()->unique();
            }

            if (! Schema::hasColumn('metal_traders', 'balance')) {
                $table->decimal('balance', 10, 0)->default(0);
            }
        });
    }
};
