<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('metal_physical_delivery_requests', function (Blueprint $table) {
            $table->id();
            $table->string('tracking_code', 32)->unique();
            $table->unsignedBigInteger('metal_trader_id');
            $table->unsignedBigInteger('metal_item_id');
            $table->unsignedBigInteger('metal_trader_wallet_id');

            $table->decimal('qty', 20, 3);
            $table->string('unit', 16)->default('gram');

            $table->enum('status', [
                'PENDING',
                'READY_FOR_PICKUP',
                'DELIVERED',
                'CANCELLED',
                'REJECTED',
            ])->default('PENDING')->index('mpdr_status_idx');

            // کارمزد snapshot (fee_rate_percent مثلاً 0.5 یعنی ۰٫۵٪)
            $table->decimal('fee_rate_percent', 10, 6);
            $table->decimal('fee_unit_price', 20, 0)->comment('قیمت فروش مبنا به ازای واحد فیزیکی');
            $table->string('fee_pricing_unit_id', 32)->nullable();
            $table->decimal('fee_amount', 20, 0)->comment('کارمزد تومانی نهایی');
            $table->timestamp('fee_recognized_at')->nullable();
            $table->timestamp('fee_refunded_at')->nullable();
            $table->unsignedBigInteger('fee_wallet_tx_id')->nullable();
            $table->unsignedBigInteger('fee_refund_wallet_tx_id')->nullable();

            // منبع تحویل (فقط پس از DELIVERED پر می‌شود)
            $table->enum('delivery_source', [
                'TREASURY_PHYSICAL',
                'SHOP_UNREGISTERED',
            ])->nullable();
            $table->unsignedBigInteger('treasury_lot_id')->nullable();
            $table->unsignedBigInteger('treasury_movement_id')->nullable();

            $table->unsignedBigInteger('prepared_by')->nullable();
            $table->timestamp('prepared_at')->nullable();
            $table->unsignedBigInteger('delivered_by')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->unsignedBigInteger('cancelled_by')->nullable();
            $table->string('cancelled_by_type', 32)->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason', 1000)->nullable();
            $table->unsignedBigInteger('rejected_by')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->string('reject_reason', 1000)->nullable();

            $table->timestamp('metal_reserved_at')->nullable();
            $table->timestamp('metal_released_at')->nullable();
            $table->timestamp('metal_consumed_at')->nullable();

            $table->string('idempotency_key', 128)->nullable()->unique('mpdr_idempotency_uq');
            $table->string('deliver_idempotency_key', 128)->nullable()->unique('mpdr_deliver_idem_uq');
            $table->text('notes')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->foreign('metal_trader_id', 'mpdr_trader_fk')
                ->references('id')->on('metal_traders')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreign('metal_item_id', 'mpdr_item_fk')
                ->references('id')->on('metal_items')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreign('metal_trader_wallet_id', 'mpdr_wallet_fk')
                ->references('id')->on('metal_trader_wallets')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreign('treasury_lot_id', 'mpdr_lot_fk')
                ->references('id')->on('metal_treasury_lots')->cascadeOnUpdate()->nullOnDelete();

            $table->index(['metal_trader_id', 'status'], 'mpdr_trader_status_idx');
            $table->index(['metal_item_id', 'status'], 'mpdr_item_status_idx');
            $table->index(['created_at'], 'mpdr_created_idx');
        });

        Schema::create('metal_physical_delivery_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('metal_physical_delivery_request_id');
            $table->string('event_type', 64);
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32)->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('actor_type', 32)->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->foreign('metal_physical_delivery_request_id', 'mpde_request_fk')
                ->references('id')
                ->on('metal_physical_delivery_requests')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->index(['metal_physical_delivery_request_id', 'created_at'], 'mpde_request_created_idx');
        });

        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->unsignedBigInteger('metal_physical_delivery_request_id')->nullable()->after('metal_order_id');
            $table->foreign('metal_physical_delivery_request_id', 'wt_pdr_fk')
                ->references('id')
                ->on('metal_physical_delivery_requests')
                ->cascadeOnUpdate()
                ->nullOnDelete();
        });

        // MySQL enum: افزودن PHYSICAL_DELIVERY_OUT
        DB::statement("ALTER TABLE `metal_treasury_movements` MODIFY `movement_type` ENUM(
            'IN','RESERVE','ALLOCATE','RELEASE','ADJUST','EXTERNAL_OUT','PHYSICAL_DELIVERY_OUT'
        ) NOT NULL");

        Schema::table('metal_treasury_movements', function (Blueprint $table) {
            $table->unsignedBigInteger('physical_delivery_request_id')->nullable()->after('attempt_id');
            $table->index('physical_delivery_request_id', 'mtm_pdr_idx');
        });

        if (!DB::table('permissions')->where('name', 'manage-metal-physical-delivery')->exists()) {
            DB::table('permissions')->insert([
                'name' => 'manage-metal-physical-delivery',
                'display_name' => 'مدیریت تحویل فیزیکی فلز',
                'description' => 'manage metal physical delivery requests',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if (!DB::table('settings')->where('option_key', 'physical_delivery_fee_percent')->exists()) {
            DB::table('settings')->insert([
                'option_key' => 'physical_delivery_fee_percent',
                'option_name' => 'نرخ کارمزد تحویل فیزیکی فلز (درصد)',
                'option_value' => '0.5',
            ]);
        }
    }

    public function down(): void
    {
        DB::table('settings')->where('option_key', 'physical_delivery_fee_percent')->delete();
        DB::table('permissions')->where('name', 'manage-metal-physical-delivery')->delete();

        Schema::table('metal_treasury_movements', function (Blueprint $table) {
            $table->dropIndex('mtm_pdr_idx');
            $table->dropColumn('physical_delivery_request_id');
        });

        DB::statement("ALTER TABLE `metal_treasury_movements` MODIFY `movement_type` ENUM(
            'IN','RESERVE','ALLOCATE','RELEASE','ADJUST','EXTERNAL_OUT'
        ) NOT NULL");

        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->dropForeign('wt_pdr_fk');
            $table->dropColumn('metal_physical_delivery_request_id');
        });

        Schema::dropIfExists('metal_physical_delivery_events');
        Schema::dropIfExists('metal_physical_delivery_requests');
    }
};
