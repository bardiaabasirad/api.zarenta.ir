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
        DB::table('metal_orders')
            ->whereNotNull('extra_data')
            ->whereRaw("JSON_EXTRACT(extra_data, '$.frozen') = ?", ['money'])
            ->chunkById(100, function ($orders) {
                foreach ($orders as $order) {
                    $extraData = json_decode($order->extra_data, true);
                    $extraData['frozen'] = 'cash';

                    DB::table('metal_orders')
                        ->where('id', $order->id)
                        ->update(['extra_data' => json_encode($extraData, JSON_UNESCAPED_UNICODE)]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('metal_orders')
            ->whereNotNull('extra_data')
            ->whereRaw("JSON_EXTRACT(extra_data, '$.frozen') = ?", ['cash'])
            ->chunkById(100, function ($orders) {
                foreach ($orders as $order) {
                    $extraData = json_decode($order->extra_data, true);
                    $extraData['frozen'] = 'money';

                    DB::table('metal_orders')
                        ->where('id', $order->id)
                        ->update(['extra_data' => json_encode($extraData, JSON_UNESCAPED_UNICODE)]);
                }
            });
    }
};
