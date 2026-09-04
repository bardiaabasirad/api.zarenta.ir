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
        Schema::create('product_name_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(\App\Models\ReferenceChannel::class)
                ->constrained()
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->string('english_key', 255);
            $table->string('persian_key', 255);
            $table->string('persian_name', 255);
            $table->enum('is_active', ['active', 'inactive'])->default('active');
            $table->timestamps();

            // اضافه کردن ایندکس‌ها
            $table->index('persian_name', 'idx_persian_name');
            $table->index('english_key', 'idx_english_key');
            $table->index('is_active', 'idx_is_active');

            // ایندکس ترکیبی برای جستجوی سریع‌تر
            $table->index(['reference_channel_id', 'persian_name'], 'idx_channel_persian');
            $table->index(['reference_channel_id', 'is_active'], 'idx_channel_active');

            // ایندکس یونیک برای جلوگیری از تکراری شدن
            $table->unique(['reference_channel_id', 'persian_name'], 'unique_channel_persian');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_name_mappings');
    }
};
