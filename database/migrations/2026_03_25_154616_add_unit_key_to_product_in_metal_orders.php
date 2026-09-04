<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('metal_orders')
            ->whereRaw("JSON_EXTRACT(product, '$.unit') IS NULL")
            ->update([
                'product' => DB::raw("JSON_SET(product, '$.unit', 'گرم')")
            ]);
    }

    public function down(): void
    {
        DB::table('metal_orders')->update([
            'product' => DB::raw("JSON_REMOVE(product, '$.unit')")
        ]);
    }
};
