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
        Schema::table('metal_orders_without_frozen', function (Blueprint $table) {
            DB::table('metal_orders')
                ->whereNotNull('extra_data')
                ->chunkById(100, function ($orders) {
                    foreach ($orders as $order) {
                        $extraData = json_decode($order->extra_data, true);

                        if (is_array($extraData) && !isset($extraData['frozen'])) {
                            $extraData['frozen'] = 'metal';

                            DB::table('metal_orders')
                                ->where('id', $order->id)
                                ->update([
                                    'extra_data' => json_encode($extraData, JSON_UNESCAPED_UNICODE)
                                ]);
                        }
                    }
                });
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('metal_orders_without_frozen', function (Blueprint $table) {
            DB::table('metal_orders')
                ->whereNotNull('extra_data')
                ->chunkById(100, function ($orders) {
                    foreach ($orders as $order) {
                        $extraData = json_decode($order->extra_data, true);

                        if (is_array($extraData) && isset($extraData['frozen']) && $extraData['frozen'] === 'metal') {
                            unset($extraData['frozen']);

                            DB::table('metal_orders')
                                ->where('id', $order->id)
                                ->update([
                                    'extra_data' => json_encode($extraData, JSON_UNESCAPED_UNICODE)
                                ]);
                        }
                    }
                });
        });
    }
};
