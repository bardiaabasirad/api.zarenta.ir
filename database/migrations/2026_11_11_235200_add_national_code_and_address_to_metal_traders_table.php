<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('metal_traders', function (Blueprint $table) {
            $table->string('national_code', 10)->nullable()->after('phone');
            $table->text('address')->nullable()->after('national_code');
            $table->unique('national_code');
        });
    }

    public function down(): void
    {
        Schema::table('metal_traders', function (Blueprint $table) {
            $table->dropUnique(['national_code']);
            $table->dropColumn(['national_code', 'address']);
        });
    }
};
