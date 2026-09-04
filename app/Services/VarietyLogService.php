<?php

namespace App\Services;

use App\Models\VarietyLog;

class VarietyLogService
{
    /**
     * @param $variety_id
     * @param $old_value
     * @param $new_value
     * @param null $details
     * @return void
     */
    public static function make($variety, $count, $details = null): void
    {
        $varietyLog = new VarietyLog();
        $varietyLog->variety_id = $variety->id;
        $varietyLog->old_values = ['count' => (string) $variety->count];
        $varietyLog->new_values = ['count' => (string) ($variety->count - (int) $count)];
        $varietyLog->details = $details;

        $varietyLog->save();
    }
}
