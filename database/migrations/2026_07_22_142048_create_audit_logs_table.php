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
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();

            // چه کسی این عملیات را انجام داده
            $table->string('actor_type', 120)->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();

            // روی چه چیزی اثر گذاشته
            $table->string('subject_type', 120)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();

            // مثلا user_banned, admin_created, trader_api_key_rotated
            $table->string('action', 100);

            // created, updated, deleted, status_changed, approved, revoked
            $table->string('category', 50)->nullable();

            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();

            // موفق/ناموفق بودن عملیات
            $table->string('status', 20)->default('success');

            // دلیل شکست یا توضیح کوتاه
            $table->string('reason', 150)->nullable();

            // داده های تکمیلی؛ مثلا old/new values محدود و sanitize شده
            $table->json('meta')->nullable();

            $table->timestamp('occurred_at');

            $table->timestamps();

            $table->index(['actor_type', 'actor_id'], 'audit_logs_actor_index');
            $table->index(['subject_type', 'subject_id'], 'audit_logs_subject_index');
            $table->index(['action', 'occurred_at'], 'audit_logs_action_occurred_index');
            $table->index(['status', 'occurred_at'], 'audit_logs_status_occurred_index');
            $table->index('ip_address');
            $table->index('occurred_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
