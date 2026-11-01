<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('metal_trader_bank_account_rejection_reasons', function (Blueprint $table) {
            $table->id();
            $table->string('value');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('metal_trader_bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('metal_trader_id');
            $table->string('sheba', 26);
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending')->index();
            $table->unsignedBigInteger('rejection_reason_id')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('metal_trader_id', 'mtba_trader_fk')
                ->references('id')->on('metal_traders')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreign('rejection_reason_id', 'mtba_reject_reason_fk')
                ->references('id')->on('metal_trader_bank_account_rejection_reasons')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->foreign('reviewed_by', 'mtba_reviewed_by_fk')
                ->references('id')->on('admins')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->index(['metal_trader_id', 'sheba'], 'mtba_trader_sheba_idx');
            $table->index(['status', 'created_at'], 'mtba_status_created_idx');
        });

        Schema::create('metal_trader_notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('metal_trader_id');
            $table->string('title');
            $table->text('content');
            $table->string('link', 255)->nullable();
            $table->string('type', 64)->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->foreign('metal_trader_id', 'mtn_trader_fk')
                ->references('id')->on('metal_traders')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->index(['metal_trader_id', 'read_at'], 'mtn_trader_read_idx');
        });

        // optional link on admin notifications for click-through
        Schema::table('notifications', function (Blueprint $table) {
            $table->string('link', 255)->nullable()->after('content');
        });

        DB::table('metal_trader_bank_account_rejection_reasons')->insert([
            [
                'value' => 'شماره شبا متعلق به صاحب حساب نیست',
                'sort_order' => 1,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'value' => 'شماره شبا نامعتبر است',
                'sort_order' => 2,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'value' => 'حساب بانکی مسدود یا راکد است',
                'sort_order' => 3,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'value' => 'اطلاعات بانکی ناقص یا نادرست است',
                'sort_order' => 4,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        if (!DB::table('permissions')->where('name', 'manage-metal-trader-bank-accounts')->exists()) {
            DB::table('permissions')->insert([
                'name' => 'manage-metal-trader-bank-accounts',
                'display_name' => 'مدیریت حساب‌های بانکی تریدرها',
                'description' => 'approve and reject metal trader bank accounts',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('permissions')->where('name', 'manage-metal-trader-bank-accounts')->delete();

        Schema::table('notifications', function (Blueprint $table) {
            $table->dropColumn('link');
        });

        Schema::dropIfExists('metal_trader_notifications');
        Schema::dropIfExists('metal_trader_bank_accounts');
        Schema::dropIfExists('metal_trader_bank_account_rejection_reasons');
    }
};
