<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dealing_group_metal_item', function (Blueprint $table) {
            $table->string('target_unit_id', 20)
                ->default('mazane')
                ->after('tolerance_type')
                ->comment('واحد نمایش نرخ برای این گروه معاملاتی');
        });

        $rows = DB::table('dealing_group_metal_item')->get();
        foreach ($rows as $row) {
            $targetUnitId = ($row->display_mode ?? 'quotation') === 'per_gram'
                ? 'gram'
                : 'mazane';

            $metalItem = DB::table('metal_items')->where('id', $row->metal_item_id)->first();
            if ($metalItem && ($metalItem->unit ?? null) === 'count') {
                $targetUnitId = 'count';
            }

            DB::table('dealing_group_metal_item')
                ->where('dealing_group_id', $row->dealing_group_id)
                ->where('metal_item_id', $row->metal_item_id)
                ->update(['target_unit_id' => $targetUnitId]);
        }

        Schema::table('dealing_group_metal_item', function (Blueprint $table) {
            $table->foreign('target_unit_id')
                ->references('id')
                ->on('units')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->dropColumn('display_mode');
        });
    }

    public function down(): void
    {
        Schema::table('dealing_group_metal_item', function (Blueprint $table) {
            $table->enum('display_mode', ['quotation', 'per_gram'])
                ->default('quotation')
                ->after('tolerance_type');
        });

        $rows = DB::table('dealing_group_metal_item')->get();
        foreach ($rows as $row) {
            $displayMode = ($row->target_unit_id ?? 'mazane') === 'gram'
                ? 'per_gram'
                : 'quotation';

            DB::table('dealing_group_metal_item')
                ->where('dealing_group_id', $row->dealing_group_id)
                ->where('metal_item_id', $row->metal_item_id)
                ->update(['display_mode' => $displayMode]);
        }

        Schema::table('dealing_group_metal_item', function (Blueprint $table) {
            $table->dropForeign(['target_unit_id']);
            $table->dropColumn('target_unit_id');
        });
    }
};
