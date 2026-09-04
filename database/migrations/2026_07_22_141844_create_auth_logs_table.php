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
        Schema::create('auth_logs', function (Blueprint $table) {
            $table->id();

            // User/Admin/MetalTrader
            $table->string('authenticatable_type', 120)->nullable();
            $table->unsignedBigInteger('authenticatable_id')->nullable();

            // web, admin, trader, api
            $table->string('guard_name', 50)->nullable();

            // login_success, login_failed, logout, lockout, password_reset
            $table->string('event', 50);

            // success, failed, blocked
            $table->string('status', 20);

            // شماره موبایل / کد ملی / هر شناسه ای که کاربر با آن تلاش کرده وارد شود
            $table->string('identity', 191)->nullable();

            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();

            // در لاگین های session-based
            $table->string('session_id', 191)->nullable();

            // مثلا web, admin_panel, trader_panel, api
            $table->string('channel', 50)->nullable();

            // مثلا invalid_credentials, inactive_account, banned, locked_out
            $table->string('reason', 100)->nullable();

            // اطلاعات تکمیلی مثل request_id, headers محدود, otp flow, ...
            $table->json('meta')->nullable();

            $table->timestamp('occurred_at');

            $table->timestamps();

            $table->index(['authenticatable_type', 'authenticatable_id'], 'auth_logs_authenticatable_index');
            $table->index(['guard_name', 'event'], 'auth_logs_guard_event_index');
            $table->index(['status', 'occurred_at'], 'auth_logs_status_occurred_index');
            $table->index('identity');
            $table->index('ip_address');
            $table->index('occurred_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('auth_logs');
    }
};
