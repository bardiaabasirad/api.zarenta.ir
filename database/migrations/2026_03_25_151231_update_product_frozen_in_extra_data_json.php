<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $map = [
            'gold' => 'metal',
        ];

        foreach ($map as $old => $new) {
            DB::table('metal_orders')
                ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(extra_data, '$.frozen')) = ?", [$old])
                ->update([
                    'extra_data' => DB::raw("JSON_SET(extra_data, '$.frozen', '$new')")
                ]);
        }
    }

    public function down(): void
    {
        $map = [
            'gold' => 'metal',
        ];

        foreach ($map as $old => $new) {
            DB::table('metal_orders')
                ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(extra_data, '$.frozen')) = ?", [$new])
                ->update([
                    'extra_data' => DB::raw("JSON_SET(extra_data, '$.frozen', '$old')")
                ]);
        }
    }
};
