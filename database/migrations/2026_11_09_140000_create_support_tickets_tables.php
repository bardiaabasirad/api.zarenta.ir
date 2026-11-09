<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('subject', 255);
            $table->enum('status', [
                'open',
                'answered',
                'closed',
                'auto_closed',
            ])->default('open')->index();
            $table->string('creator_type', 32);
            $table->unsignedBigInteger('creator_id');
            $table->timestamp('last_message_at')->nullable()->index();
            $table->unsignedBigInteger('last_replied_admin_id')->nullable();
            $table->unsignedTinyInteger('rating')->nullable();
            $table->timestamp('rated_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->string('closed_by_type', 32)->nullable();
            $table->unsignedBigInteger('closed_by_id')->nullable();
            $table->timestamps();

            $table->index(['creator_type', 'creator_id'], 'st_creator_idx');
            $table->index(['status', 'last_message_at'], 'st_status_last_msg_idx');
            $table->foreign('last_replied_admin_id', 'st_last_admin_fk')
                ->references('id')->on('admins')->nullOnDelete();
        });

        Schema::create('support_ticket_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('support_ticket_id');
            $table->text('body');
            $table->string('image_path', 500)->nullable();
            $table->string('sender_type', 32);
            $table->unsignedBigInteger('sender_id');
            $table->timestamps();

            $table->foreign('support_ticket_id', 'stm_ticket_fk')
                ->references('id')
                ->on('support_tickets')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->index(['support_ticket_id', 'created_at'], 'stm_ticket_created_idx');
            $table->index(['sender_type', 'sender_id'], 'stm_sender_idx');
        });

        Schema::create('support_ticket_operators', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('support_ticket_id');
            $table->unsignedBigInteger('admin_id');
            $table->timestamp('first_replied_at')->nullable();
            $table->timestamp('last_replied_at')->nullable();
            $table->unsignedInteger('replies_count')->default(0);
            $table->timestamps();

            $table->unique(['support_ticket_id', 'admin_id'], 'sto_ticket_admin_uq');
            $table->foreign('support_ticket_id', 'sto_ticket_fk')
                ->references('id')
                ->on('support_tickets')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreign('admin_id', 'sto_admin_fk')
                ->references('id')
                ->on('admins')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
        });

        if (!DB::table('permissions')->where('name', 'manage-support-tickets')->exists()) {
            $permissionId = DB::table('permissions')->insertGetId([
                'name' => 'manage-support-tickets',
                'display_name' => 'مدیریت تیکت‌های پشتیبانی',
                'description' => 'manage support tickets',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $roleIds = DB::table('roles')->pluck('id');
            foreach ($roleIds as $roleId) {
                DB::table('permission_role')->insertOrIgnore([
                    'permission_id' => $permissionId,
                    'role_id' => $roleId,
                ]);
            }
        }
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')->where('name', 'manage-support-tickets')->value('id');
        if ($permissionId) {
            DB::table('permission_role')->where('permission_id', $permissionId)->delete();
            DB::table('permissions')->where('id', $permissionId)->delete();
        }

        Schema::dropIfExists('support_ticket_operators');
        Schema::dropIfExists('support_ticket_messages');
        Schema::dropIfExists('support_tickets');
    }
};
