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
        Schema::create('gold_orders', function (Blueprint $table) {
            $table->id();
            $table->decimal('tracking_code', 10,0)->unique();
            $table->json('product');
            $table->unsignedBigInteger('created_id')->nullable();
            $table->string('created_type')->nullable();
            $table->enum('status', ['pending','succeed','rejected'])->default('pending');
            $table->enum('order_type', ['sell','buy'])->default('buy');
            $table->text('extra_data')->nullable(); // توضیحات
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gold_orders');
    }
};
