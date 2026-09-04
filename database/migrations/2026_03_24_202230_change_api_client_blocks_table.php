<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // حذف FK ها قبل از rename
        Schema::table('api_client_blocks', function (Blueprint $table) {
            $table->dropForeign('api_client_blocks_api_client_id_foreign');
            $table->dropForeign('api_client_blocks_admin_id_foreign');
        });

        // تغییر نام جدول
        Schema::rename('api_client_blocks', 'metal_trader_suspensions');

        // تغییرات ستون‌ها
        Schema::table('metal_trader_suspensions', function (Blueprint $table) {
            $table->renameColumn('api_client_id', 'metal_trader_id');
            $table->renameColumn('admin_id', 'suspended_by');
            $table->renameColumn('blocked_at', 'suspended_at');
            $table->renameColumn('unblocked_at', 'unsuspended_at');

            $table->unsignedBigInteger('unsuspended_by')->nullable()->after('suspended_by');

            $table->foreign('metal_trader_id')
                ->references('id')
                ->on('metal_traders')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->foreign('suspended_by')
                ->references('id')
                ->on('admins')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->foreign('unsuspended_by')
                ->references('id')
                ->on('admins')
                ->nullOnDelete()
                ->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::table('metal_trader_suspensions', function (Blueprint $table) {
            $table->dropForeign(['metal_trader_id']);
            $table->dropForeign(['suspended_by']);
            $table->dropForeign(['unsuspended_by']);

            $table->dropColumn('unsuspended_by');

            $table->renameColumn('metal_trader_id', 'api_client_id');
            $table->renameColumn('suspended_by', 'admin_id');
            $table->renameColumn('suspended_at', 'blocked_at');
            $table->renameColumn('unsuspended_at', 'unblocked_at');

            $table->foreign('api_client_id')
                ->references('id')
                ->on('api_clients')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->foreign('admin_id')
                ->references('id')
                ->on('admins')
                ->nullOnDelete()
                ->cascadeOnUpdate();
        });

        Schema::rename('metal_trader_suspensions', 'api_client_blocks');
    }
};
