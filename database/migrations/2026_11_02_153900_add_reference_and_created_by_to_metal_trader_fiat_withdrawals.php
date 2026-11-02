<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('metal_trader_fiat_withdrawals', function (Blueprint $table) {
            $table->string('reference_number', 191)->nullable()->after('notes');
            $table->unsignedBigInteger('created_by')->nullable()->after('reference_number');

            $table->foreign('created_by', 'mtfw_created_by_fk')
                ->references('id')->on('admins')
                ->cascadeOnUpdate()
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('metal_trader_fiat_withdrawals', function (Blueprint $table) {
            $table->dropForeign('mtfw_created_by_fk');
            $table->dropColumn(['reference_number', 'created_by']);
        });
    }
};
