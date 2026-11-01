<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('metal_treasury_physical_purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('metal_item_id')->constrained('metal_items')->cascadeOnUpdate();
            $table->enum('maturity_code', ['T0', 'T1', 'T2']);
            $table->date('settlement_date')->nullable();
            $table->decimal('qty', 20, 3);
            $table->decimal('unit_cost', 20, 0);
            $table->string('document_number', 64)->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by_id')->nullable();
            $table->string('created_by_type', 32)->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('metal_treasury_lots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('metal_item_id')->constrained('metal_items')->cascadeOnUpdate();
            $table->enum('maturity_code', ['T0', 'T1', 'T2'])->index();
            $table->date('settlement_date')->nullable()->index();
            $table->enum('source_type', [
                'PARTNER',
                'USER_WALLET_SELL',
                'PHYSICAL_PURCHASE',
                'MANUAL_ADJUSTMENT',
                'MIGRATION',
            ]);
            $table->unsignedBigInteger('source_id')->nullable();
            $table->decimal('initial_qty', 20, 3);
            $table->decimal('remaining_qty', 20, 3);
            $table->decimal('unit_cost', 20, 0)->default(0);
            $table->timestamp('purchased_at')->useCurrent();
            $table->enum('status', ['OPEN', 'DEPLETED', 'CLOSED_EXTERNAL'])
                ->default('OPEN')
                ->index();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(
                ['metal_item_id', 'maturity_code', 'status', 'purchased_at'],
                'mtl_item_maturity_status_purchased_idx'
            );
        });

        Schema::create('metal_treasury_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lot_id')
                ->constrained('metal_treasury_lots')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->enum('movement_type', [
                'IN',
                'RESERVE',
                'ALLOCATE',
                'RELEASE',
                'ADJUST',
                'EXTERNAL_OUT',
            ]);
            $table->decimal('qty', 20, 3);
            $table->decimal('unit_cost', 20, 0)->nullable();
            $table->foreignId('metal_order_id')
                ->nullable()
                ->constrained('metal_orders')
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->unsignedBigInteger('allocation_id')->nullable()->index();
            $table->unsignedBigInteger('attempt_id')->nullable()->index();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('actor_type', 32)->nullable();
            $table->string('idempotency_key', 64)->unique();
            $table->string('reason', 255)->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['lot_id', 'movement_type'], 'mtm_lot_type_idx');
        });

        Schema::create('metal_order_coverage_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('metal_order_id')
                ->constrained('metal_orders')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->enum('channel', [
                'PARTNER',
                'PHYSICAL',
                'TREASURY',
                'USER_SELL',
            ]);
            $table->enum('status', [
                'pending',
                'sent',
                'succeeded',
                'failed',
                'timeout',
                'invalid',
            ])->default('pending')->index('moca_status_idx');
            $table->string('idempotency_key', 64)->unique('moca_idempotency_uq');
            $table->decimal('requested_qty', 20, 3);
            $table->decimal('executed_qty', 20, 3)->nullable();
            $table->decimal('executed_rate', 20, 0)->nullable();
            $table->string('partner_order_id', 64)->nullable();
            $table->foreignId('metal_order_exchange_id')
                ->nullable()
                ->constrained('metal_order_exchanges')
                ->nullOnDelete();
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->string('error_code', 64)->nullable();
            $table->string('error_message', 500)->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('actor_type', 32)->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['metal_order_id', 'status'], 'moca_order_status_idx');
        });

        Schema::create('metal_order_coverage_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('metal_order_id')
                ->constrained('metal_orders')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreignId('metal_item_id')->constrained('metal_items')->cascadeOnUpdate();
            $table->enum('source_type', [
                'TREASURY',
                'PARTNER',
                'USER_WALLET_SELL',
                'PHYSICAL_PURCHASE',
            ]);
            $table->unsignedBigInteger('source_id')->nullable();
            $table->foreignId('lot_id')
                ->nullable()
                ->constrained('metal_treasury_lots')
                ->nullOnDelete();
            $table->foreignId('attempt_id')
                ->nullable()
                ->constrained('metal_order_coverage_attempts')
                ->nullOnDelete();
            $table->enum('maturity_code', ['T0', 'T1', 'T2']);
            $table->decimal('qty', 20, 3);
            $table->decimal('customer_unit_price', 20, 0);
            $table->decimal('coverage_unit_cost', 20, 0);
            $table->decimal('realized_pnl', 20, 0);
            $table->timestamp('allocated_at')->useCurrent();
            $table->unsignedBigInteger('allocated_by_id')->nullable();
            $table->string('allocated_by_type', 32)->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->unsignedBigInteger('reversal_movement_id')->nullable();
            $table->string('idempotency_key', 64)->unique('mocal_idempotency_uq');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['metal_order_id', 'reversed_at'], 'mocal_order_reversed_idx');
            $table->index(['lot_id', 'reversed_at'], 'mocal_lot_reversed_idx');
        });

        Schema::table('metal_treasury_movements', function (Blueprint $table) {
            $table->foreign('allocation_id', 'mtm_allocation_fk')
                ->references('id')
                ->on('metal_order_coverage_allocations')
                ->nullOnDelete();
            $table->foreign('attempt_id', 'mtm_attempt_fk')
                ->references('id')
                ->on('metal_order_coverage_attempts')
                ->nullOnDelete();
        });

        Schema::table('metal_orders', function (Blueprint $table) {
            $table->enum('coverage_status', [
                'UNCOVERED',
                'PARTIALLY_COVERED',
                'FULLY_COVERED',
                'OVERDUE',
            ])->nullable()->after('settlement_status')->index('mo_coverage_status_idx');
            $table->decimal('covered_qty', 20, 3)->nullable()->after('coverage_status');
            $table->decimal('open_qty', 20, 3)->nullable()->after('covered_qty');
            $table->date('coverage_deadline')->nullable()->after('open_qty')->index('mo_coverage_deadline_idx');
            $table->timestamp('last_coverage_attempt_at')->nullable()->after('coverage_deadline');
            $table->string('last_partner_error', 500)->nullable()->after('last_coverage_attempt_at');
        });
    }

    public function down(): void
    {
        Schema::table('metal_orders', function (Blueprint $table) {
            $table->dropColumn([
                'coverage_status',
                'covered_qty',
                'open_qty',
                'coverage_deadline',
                'last_coverage_attempt_at',
                'last_partner_error',
            ]);
        });

        Schema::table('metal_treasury_movements', function (Blueprint $table) {
            $table->dropForeign('mtm_allocation_fk');
            $table->dropForeign('mtm_attempt_fk');
        });

        Schema::dropIfExists('metal_order_coverage_allocations');
        Schema::dropIfExists('metal_order_coverage_attempts');
        Schema::dropIfExists('metal_treasury_movements');
        Schema::dropIfExists('metal_treasury_lots');
        Schema::dropIfExists('metal_treasury_physical_purchases');
    }
};
