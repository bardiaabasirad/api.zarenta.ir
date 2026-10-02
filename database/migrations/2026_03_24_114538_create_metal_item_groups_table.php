<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('metal_item_groups', function (Blueprint $table) {
            $table->id();
            $table->integer('sort_order')->default(0)->index();
            $table->string('title', 191)->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('metal_item_groups');
    }
};
