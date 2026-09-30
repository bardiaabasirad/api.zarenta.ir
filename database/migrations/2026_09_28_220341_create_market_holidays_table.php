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
        Schema::create('market_holidays', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique()->comment('تاریخ میلادی (ایندکس یونیک برای جلوگیری از رکورد تکراری)');
            $table->string('jalali_date', 10)->index()->comment('مثلا 1403/05/12 برای سرچ سریع');
            $table->string('title')->nullable()->comment('علت تعطیلی (اختیاری: مثلا تاسوعا، شهادت، تعطیلی صنف)');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('market_holidays');
    }
};
