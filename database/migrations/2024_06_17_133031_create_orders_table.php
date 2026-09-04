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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('code', 8)->unique();
            $table->foreignIdFor(\App\Models\City::class)->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignIdFor(\App\Models\User::class)->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->decimal('purchase', 10,0)->unsigned()->nullable();
            $table->decimal('sale', 10,0)->unsigned();
            $table->decimal('registered_melted_gold_order_total_purchase', 10,0);
            $table->decimal('registered_melted_gold_order_total_profit', 10,0);
            $table->decimal('total_paid', 10,0)->unsigned()->default(0);
            $table->decimal('shipping_cost', 7,0)->unsigned()->default(0);
            $table->text('address')->nullable();
            $table->enum('status', ['wait_payment','reserved','paid','collecting','collected','delivered','canceled'])->default('wait_payment');
            $table->decimal('market', 8,0)->unsigned();
            $table->decimal('melted', 8,0)->unsigned()->nullable();
            $table->text('preferred_shipping_method')->nullable();
            $table->text('selected_shipping_method')->nullable();
            $table->text('delivery_details')->nullable();
            $table->string('cancellation_reason')->nullable();
            $table->text('cancellation_details')->nullable();
            $table->string('tracking_code')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('payment_deadline')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
