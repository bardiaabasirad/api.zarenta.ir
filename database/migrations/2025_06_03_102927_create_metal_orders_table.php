<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('metal_orders', function (Blueprint $table) {
            $table->id();

            $table->decimal('tracking_code', 10, 0)->unique();

            $table->string('source_order_id', 64)->nullable();

            $table->foreignId('metal_item_id')
                ->nullable()
                ->constrained('metal_items')
                ->cascadeOnUpdate();

            $table->string('product_name');
            $table->decimal('qty', 16, 3);
            $table->string('unit', 16)->nullable();
            $table->decimal('fee', 20, 2);
            $table->decimal('amount', 20, 0);

            // FK to units is added after units table exists
            // (see 2026_10_24_200100 and alter_metal_orders_drop_product_json.sql)
            $table->string('pricing_unit_id', 20)->nullable();
            $table->string('target_unit_id', 20)->nullable();

            $table->unsignedBigInteger('created_id')->nullable();
            $table->string('created_type', 32)->nullable();

            $table->enum('status', [
                'pending',
                'processing',
                'succeed',
                'rejected',
            ])->default('pending');

            $table->enum('order_type', ['sell', 'buy'])->default('buy');
            $table->text('extra_data')->nullable();
            $table->date('settlement_date')->nullable()
                ->index('idx_metal_orders_settlement_date');

            $table->enum('settlement_status', ['pending', 'settled'])
                ->default('settled')
                ->index();

            $table->timestamps();

            $table->unique(
                ['source_order_id', 'created_type', 'created_id'],
                'metal_orders_source_created_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('metal_orders');
    }
};
