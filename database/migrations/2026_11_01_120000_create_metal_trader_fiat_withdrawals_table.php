<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('metal_trader_fiat_withdrawals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('metal_trader_id');
            $table->unsignedBigInteger('metal_trader_bank_account_id');
            $table->string('sheba', 26)->comment('اسنپ‌شات شبا در زمان درخواست');
            $table->decimal('amount', 20, 0);
            $table->enum('status', ['pending', 'paid', 'rejected', 'cancelled'])
                ->default('pending')
                ->index();
            $table->unsignedBigInteger('wallet_transaction_id')->nullable();
            $table->unsignedBigInteger('refund_wallet_transaction_id')->nullable();
            $table->string('reject_reason', 1000)->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('metal_trader_id', 'mtfw_trader_fk')
                ->references('id')->on('metal_traders')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreign('metal_trader_bank_account_id', 'mtfw_bank_fk')
                ->references('id')->on('metal_trader_bank_accounts')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreign('wallet_transaction_id', 'mtfw_wallet_tx_fk')
                ->references('id')->on('wallet_transactions')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->foreign('refund_wallet_transaction_id', 'mtfw_refund_tx_fk')
                ->references('id')->on('wallet_transactions')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->foreign('reviewed_by', 'mtfw_reviewed_by_fk')
                ->references('id')->on('admins')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->index(['metal_trader_id', 'status'], 'mtfw_trader_status_idx');
            $table->index(['status', 'created_at'], 'mtfw_status_created_idx');
        });

        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->unsignedBigInteger('metal_trader_fiat_withdrawal_id')->nullable()->after('metal_physical_delivery_request_id');
            $table->foreign('metal_trader_fiat_withdrawal_id', 'wt_mtfw_fk')
                ->references('id')
                ->on('metal_trader_fiat_withdrawals')
                ->cascadeOnUpdate()
                ->nullOnDelete();
        });

        if (!DB::table('permissions')->where('name', 'manage-metal-trader-fiat-withdrawals')->exists()) {
            DB::table('permissions')->insert([
                'name' => 'manage-metal-trader-fiat-withdrawals',
                'display_name' => 'مدیریت برداشت تومانی تریدرها',
                'description' => 'review metal trader fiat withdrawal requests',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('permissions')->where('name', 'manage-metal-trader-fiat-withdrawals')->delete();

        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->dropForeign('wt_mtfw_fk');
            $table->dropColumn('metal_trader_fiat_withdrawal_id');
        });

        Schema::dropIfExists('metal_trader_fiat_withdrawals');
    }
};
