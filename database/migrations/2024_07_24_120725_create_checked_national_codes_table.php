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
        Schema::create('checked_national_codes', function (Blueprint $table) {
            $table->decimal('phone', 10, 0)->unsigned();
            $table->string('national_code', 10);
            $table->boolean('status');
            $table->timestamps();

            $table->primary(['phone','national_code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('checked_national_codes');
    }
};
