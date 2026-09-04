<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\OrderLog;

class OrderLogService
{
    /**
     * @param $order_id
     * @param $old_value
     * @param $new_value
     * @return void
     */
    public static function make($order_id, $old_value, $new_value)
    {
        $orderLog = new OrderLog();
        $orderLog->order_id = $order_id;
        $orderLog->old_values = $old_value;
        $orderLog->new_values = $new_value;
        $orderLog->save();
    }
}
