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
        Schema::create('slides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('slider_id')->constrained()->onDelete('cascade');
            $table->string('title')->nullable(); // عنوان اسلاید (اختیاری)
            $table->string('subtitle')->nullable(); // زیرعنوان کوتاه (اختیاری)
            $table->string('image_path_desktop'); // مسیر فایل تصویر روی سرور
            $table->string('image_path_tablet')->nullable(); // مسیر فایل تصویر روی سرور
            $table->string('image_path_mobile')->nullable(); // مسیر فایل تصویر روی سرور
            $table->string('link_url')->nullable(); // آدرس لینک دکمه/اسلاید

            // اکشن‌ها (Action Handling)
            $table->string('action_type')->default('link'); // میتونه 'product', 'category', 'external_link', 'none' باشه
            $table->string('action_value')->nullable(); // مثلاً شناسه محصول یا دسته‌بندی دکمه
            $table->string('action_text')->nullable(); // متن روی دکمه (مثال: "همین حالا بخرید")

            $table->integer('sort_order')->default(0); // اولویت نمایش (drag & drop)
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('slides');
    }
};
