<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('metal_order_exchanges', function (Blueprint $table) {
            $table->id();

            $table->foreignId('metal_order_id')
                ->constrained('metal_orders')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->foreignId('price_source_id')
                ->constrained('price_sources')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->enum('status', [
                'pending',
                'processing',
                'placed',
                'confirmed',
                'rejected',
                'error',
            ])->default('pending')->index();

            $table->decimal('rate', 10, 0)->nullable();

            $table->json('details')
                ->nullable()
                ->comment('Secondary reason code such as rate_changed, timeout, product_not_found');

            $table->string('message', 255)->nullable();

            $table->tinyInteger('retry_count')->default(0);

            $table->timestamp('sent_at')
                ->nullable()
                ->comment('زمان ارسال سفارش به صرافی');

            $table->timestamp('placed_at')
                ->nullable()
                ->comment('زمان ثبت نهایی سفارش در صرافی');

            $table->timestamp('resolved_at')
                ->nullable()
                ->comment('زمان مشخص شدن نتیجه سفارش در صرافی');

            $table->index(['status', 'retry_count']);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('metal_order_exchanges');
    }
};
