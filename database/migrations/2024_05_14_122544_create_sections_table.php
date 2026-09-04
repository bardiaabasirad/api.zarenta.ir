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
        Schema::create('sections', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(\App\Models\Admin::class, 'created_by')->constrained('admins')->restrictOnDelete()->restrictOnUpdate();
            $table->morphs('sectionable');
            $table->enum('status', ['active','inactive','lock'])->default('active');
            $table->enum('show_on', ['all','desktop','mobile'])->default('all');
            $table->decimal('order', 3, 0)->unsigned();
            $table->boolean('fixed')->default(false);
            $table->boolean('show_only_available_products')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sections');
    }
};
