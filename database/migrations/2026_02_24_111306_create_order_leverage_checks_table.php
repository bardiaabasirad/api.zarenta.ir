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
        Schema::create('order_leverage_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(\App\Models\GoldOrder::class)
                ->unique()
                ->constrained()
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->enum('status',['pending', 'sufficient', 'insufficient', 'connection_failed'])->default('pending');
            $table->unsignedTinyInteger('applied_leverage');
            $table->decimal('user_balance_at_check', 12,0)->default(0);
            $table->decimal('order_value', 12,0)->default(0);
            $table->decimal('shortage_amount', 12,0)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_leverage_checks');
    }
};
