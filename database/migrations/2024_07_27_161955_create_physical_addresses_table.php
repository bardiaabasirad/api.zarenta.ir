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
        Schema::create('physical_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(\App\Models\City::class)->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('title');
            $table->string('phone', 10)->nullable();
            $table->string('postal_code', 10)->nullable();
            $table->text('address');
            $table->text('working_time')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('physical_addresses');
    }
};
