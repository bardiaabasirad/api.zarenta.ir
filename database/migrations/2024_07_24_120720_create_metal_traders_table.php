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
        Schema::create('metal_traders', function (Blueprint $table) {
            $table->id();

            $table->enum('business_type', [
                'gold_dealer',
                'trader',
                'other',
            ])->default('gold_dealer');

            // ارتباط با جدول گروه‌های معاملاتی (Dealing Groups)
            $table->foreignId('dealing_group_id')
                ->nullable()
                ->constrained('dealing_groups')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->string('kimi_account_id')->nullable();
            $table->string('name')->nullable();
            $table->string('api_key', 32)->unique();
            $table->string('password')->nullable();
            $table->decimal('phone', 10, 0)->unsigned()->unique();
            $table->decimal('balance', 10, 0)->default(0);
            $table->decimal('request_made', 10, 0)->default(0);
            $table->unsignedTinyInteger('trade_leverage')->default(0);

            $table->enum('status', ['pre_registered', 'pending', 'active', 'rejected', 'inactive'])->default('pre_registered');
            $table->enum('market_opening_notification', ['active', 'inactive'])->default('active');
            $table->enum('aggregated_view_of_invoices', ['active', 'inactive'])->default('inactive');

            $table->text('webhook_url')->nullable();

            $table->text('webhook_secret')->nullable();
            $table->string('webhook_secret_version', 20)->default('v1');
            $table->boolean('webhook_enabled')->default(false);

            $table->timestamp('last_seen')->nullable();
            $table->rememberToken();
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('metal_traders');
    }
};
