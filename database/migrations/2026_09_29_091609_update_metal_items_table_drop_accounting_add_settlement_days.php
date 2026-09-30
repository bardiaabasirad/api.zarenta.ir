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
        // ۱. حذف ستون‌های منسوخ‌شده در صورت وجود
        $columnsToDrop = [
            'requires_accounting_document_id',
            'accounting_document_id',
            'kimia_product_id',
            'equivalent_to',
        ];

        foreach ($columnsToDrop as $column) {
            if (Schema::hasColumn('metal_items', $column)) {
                Schema::table('metal_items', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }

        // ۲. افزودن ستون‌های جدید فقط در صورت عدم وجود
        Schema::table('metal_items', function (Blueprint $table) {
            if (! Schema::hasColumn('metal_items', 'settlement_working_days')) {
                $table->unsignedTinyInteger('settlement_working_days')
                    ->default(0)
                    ->after('buy_sell_spread')
                    ->comment('تعداد روزهای کاری آینده جهت تسویه حساب (مثلا 2 برای T+2)');
            }

            if (! Schema::hasColumn('metal_items', 'is_spot')) {
                $table->boolean('is_spot')
                    ->default(false)
                    ->after('title');
            }

            if (! Schema::hasColumn('metal_items', 'settlement_metal_item_id')) {
                $table->foreignIdFor(\App\Models\MetalItem::class, 'settlement_metal_item_id')
                    ->nullable()
                    ->after('settlement_working_days')
                    ->constrained('metal_items')
                    ->nullOnDelete()
                    ->cascadeOnUpdate();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // ۱. حذف کلید خارجی و ستون settlement_metal_item_id در صورت وجود
        if (Schema::hasColumn('metal_items', 'settlement_metal_item_id')) {
            Schema::table('metal_items', function (Blueprint $table) {
                $table->dropForeign(['settlement_metal_item_id']);
                $table->dropColumn('settlement_metal_item_id');
            });
        }

        // ۲. حذف سایر ستون‌های جدید
        $columnsAdded = [
            'settlement_working_days',
            'is_spot',
        ];

        foreach ($columnsAdded as $column) {
            if (Schema::hasColumn('metal_items', $column)) {
                Schema::table('metal_items', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }

        // ۳. بازیابی ستون‌های قدیمی در صورت عدم وجود
        Schema::table('metal_items', function (Blueprint $table) {
            if (! Schema::hasColumn('metal_items', 'requires_accounting_document_id')) {
                $table->boolean('requires_accounting_document_id')->default(false)->after('title');
            }

            if (! Schema::hasColumn('metal_items', 'accounting_document_id')) {
                $table->string('accounting_document_id')->nullable()->after('requires_accounting_document_id');
            }

            if (! Schema::hasColumn('metal_items', 'kimia_product_id')) {
                $table->string('kimia_product_id', 191)->nullable()->after('accounting_document_id');
            }

            if (! Schema::hasColumn('metal_items', 'equivalent_to')) {
                $table->decimal('equivalent_to', 10, 3)->unsigned()->default(1)->after('is_sell_active');
            }
        });
    }
};
