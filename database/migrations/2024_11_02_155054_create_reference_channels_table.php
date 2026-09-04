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
        Schema::create('reference_channels', function (Blueprint $table) {
            $table->id();
            $table->string('channel_id', 100);
            $table->string('channel_name', 100);
            $table->unsignedTinyInteger('ordering');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reference_channels');
    }
};
