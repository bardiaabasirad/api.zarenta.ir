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
        Schema::table('metal_items', function (Blueprint $table) {
            // آیا سند حسابداری برای این رکورد ثبت شده؟
            $table->boolean('requires_accounting_document_id')
                ->default(false)
                ->after('title');

            // شناسه سند حسابداری
            $table->string('accounting_document_id')
                ->nullable()
                ->after('requires_accounting_document_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('metal_items', function (Blueprint $table) {
            $table->dropColumn([
                'requires_accounting_document_id',
                'accounting_document_id'
            ]);
        });
    }
};
