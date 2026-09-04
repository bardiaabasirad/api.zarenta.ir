<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $map = [
            'tomorrow_spot_settlement' => 'آبشده نقد فردا',
            'day_after_tomorrow_spot_settlement' => 'آبشده نقد پس فردا',
            'gold_coin_86' => 'تمام سکه ۸۶',
            'gold_half_coin_86' => 'نیم سکه ۸۶',
            'gold_quarter_coin_86' => 'ربع سکه ۸۶',
            'gold_coin_old_version' => 'تمام سکه قدیم',
        ];

        foreach ($map as $old => $new) {
            DB::table('metal_orders')
                ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(product, '$.name')) = ?", [$old])
                ->update([
                    'product' => DB::raw("JSON_SET(product, '$.name', '$new')")
                ]);
        }
    }

    public function down(): void
    {
        $map = [
            'tomorrow_spot_settlement' => 'آبشده نقد فردا',
            'day_after_tomorrow_spot_settlement' => 'آبشده نقد پس فردا',
            'gold_coin_86' => 'تمام سکه ۸۶',
            'gold_half_coin_86' => 'نیم سکه ۸۶',
            'gold_quarter_coin_86' => 'ربع سکه ۸۶',
            'gold_coin_old_version' => 'تمام سکه قدیم',
        ];

        foreach ($map as $old => $new) {
            DB::table('metal_orders')
                ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(product, '$.name')) = ?", [$new])
                ->update([
                    'product' => DB::raw("JSON_SET(product, '$.name', '$old')")
                ]);
        }
    }
};
