<?php

use App\Models\Order;
use App\Models\PaymentGateway;
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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Order::class)->nullable()->constrained()->nullOnDelete()->cascadeOnUpdate();
            $table->foreignIdFor(PaymentGateway::class)->nullable()->constrained()->nullOnDelete()->cascadeOnUpdate();
            $table->string('purchase_id')->nullable();
            $table->string('amount')->nullable();
            $table->string('status')->nullable();
            $table->text('details')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
