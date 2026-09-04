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
        // تغییر نام جدول
        Schema::rename('reference_channels', 'price_sources');

        // تغییر نام ستون‌ها و حذف ستون ordering
        Schema::table('price_sources', function (Blueprint $table) {
            $table->dropColumn('channel_id');
            $table->renameColumn('channel_name', 'name');
            $table->dropColumn('ordering');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // بازگرداندن ستون‌ها
        Schema::table('price_sources', function (Blueprint $table) {
            $table->string('channel_id')->nullable()->after('id');
            $table->renameColumn('name', 'channel_name');
            $table->unsignedTinyInteger('ordering')->nullable();
        });

        // بازگرداندن نام جدول
        Schema::rename('price_sources', 'reference_channels');
    }
};
