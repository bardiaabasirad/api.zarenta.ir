<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('metal_settlement_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('metal_trader_id')
                ->constrained('metal_traders')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreignId('commitment_metal_item_id')
                ->constrained('metal_items')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreignId('spot_metal_item_id')
                ->constrained('metal_items')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->date('settlement_date');
            $table->enum('status', ['open', 'fulfilled', 'defaulted'])->default('open')->index();
            $table->enum('obligation_side', ['fiat', 'metal', 'both', 'none'])->default('none');
            $table->decimal('net_metal', 20, 3)->default(0);
            $table->decimal('net_fiat', 20, 0)->default(0);
            $table->decimal('cash_balance_after', 20, 0)->default(0);
            $table->decimal('spot_metal_balance_after', 20, 3)->default(0);
            $table->timestamp('fulfilled_at')->nullable();
            $table->unsignedBigInteger('fulfilled_by')->nullable();
            $table->timestamp('defaulted_at')->nullable();
            $table->unsignedBigInteger('defaulted_by')->nullable();
            $table->decimal('penalty_one_percent', 20, 0)->nullable();
            $table->decimal('replacement_loss', 20, 0)->nullable();
            $table->decimal('penalty_total', 20, 0)->nullable();
            $table->boolean('partner_trade_used')->default(false);
            $table->json('extra_data')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['status', 'settlement_date'], 'msc_status_date_idx');
            $table->index(['metal_trader_id', 'status'], 'msc_trader_status_idx');
            $table->unique(
                ['metal_trader_id', 'commitment_metal_item_id', 'settlement_date'],
                'msc_trader_item_date_uidx'
            );
        });

        Schema::create('metal_settlement_case_order', function (Blueprint $table) {
            $table->id();
            $table->foreignId('metal_settlement_case_id')
                ->constrained('metal_settlement_cases')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreignId('metal_order_id')
                ->constrained('metal_orders')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['metal_settlement_case_id', 'metal_order_id'], 'msc_order_uidx');
        });

        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->unsignedBigInteger('metal_settlement_case_id')->nullable()->after('metal_trader_fiat_withdrawal_id');
            $table->foreign('metal_settlement_case_id', 'wt_msc_fk')
                ->references('id')
                ->on('metal_settlement_cases')
                ->cascadeOnUpdate()
                ->nullOnDelete();
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE `wallet_transactions` MODIFY `type` ENUM(
                'deposit',
                'withdraw',
                'block_collateral',
                'unblock_collateral',
                'metal_commitment_in',
                'metal_commitment_out',
                'settlement_metal_in',
                'settlement_metal_out',
                'fiat_debt_in',
                'fiat_credit_in',
                'settlement_fiat_transfer',
                'penalty_loss',
                'settlement_correction_metal',
                'settlement_correction_fiat',
                'settlement_delivery_in'
            ) NOT NULL");
        }

        if (! DB::table('permissions')->where('name', 'manage-metal-settlement-cases')->exists()) {
            DB::table('permissions')->insert([
                'name' => 'manage-metal-settlement-cases',
                'display_name' => 'مدیریت تعهدات سررسید',
                'description' => 'manage metal settlement fulfillment cases',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('permissions')->where('name', 'manage-metal-settlement-cases')->delete();

        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->dropForeign('wt_msc_fk');
            $table->dropColumn('metal_settlement_case_id');
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE `wallet_transactions` MODIFY `type` ENUM(
                'deposit',
                'withdraw',
                'block_collateral',
                'unblock_collateral',
                'metal_commitment_in',
                'metal_commitment_out',
                'settlement_metal_in',
                'settlement_metal_out',
                'fiat_debt_in',
                'fiat_credit_in',
                'settlement_fiat_transfer',
                'penalty_loss'
            ) NOT NULL");
        }

        Schema::dropIfExists('metal_settlement_case_order');
        Schema::dropIfExists('metal_settlement_cases');
    }
};
