<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('metal_items', function (Blueprint $table) {
            $table->string('pricing_unit_id', 20)
                ->default('mazane')
                ->after('unit')
                ->comment('واحد مبنای ثبت نرخ خام در selected_metal_prices');
        });

        DB::table('metal_items')->orderBy('id')->chunkById(100, function ($items) {
            foreach ($items as $item) {
                $pricingUnitId = ($item->unit ?? null) === 'count' ? 'count' : 'mazane';

                DB::table('metal_items')
                    ->where('id', $item->id)
                    ->update(['pricing_unit_id' => $pricingUnitId]);
            }
        });

        Schema::table('metal_items', function (Blueprint $table) {
            $table->foreign('pricing_unit_id')
                ->references('id')
                ->on('units')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });

        // metal_orders.pricing_unit_id / target_unit_id columns exist from create migration
        // but units table is created in 2026_10_24_200000 — attach FKs here.
        Schema::table('metal_orders', function (Blueprint $table) {
            $table->foreign('pricing_unit_id')
                ->references('id')
                ->on('units')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreign('target_unit_id')
                ->references('id')
                ->on('units')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('metal_orders', function (Blueprint $table) {
            $table->dropForeign(['pricing_unit_id']);
            $table->dropForeign(['target_unit_id']);
        });

        Schema::table('metal_items', function (Blueprint $table) {
            $table->dropForeign(['pricing_unit_id']);
            $table->dropColumn('pricing_unit_id');
        });
    }
};
