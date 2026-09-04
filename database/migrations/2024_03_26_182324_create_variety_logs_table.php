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
        Schema::create('variety_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(\App\Models\Variety::class)->constrained()->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignIdFor(\App\Models\Admin::class, 'log_by')->nullable()->constrained('admins')->cascadeOnDelete()->cascadeOnUpdate();
            $table->text('new_values')->nullable();
            $table->text('old_values')->nullable();
            $table->text('details')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('variety_logs');
    }
};
