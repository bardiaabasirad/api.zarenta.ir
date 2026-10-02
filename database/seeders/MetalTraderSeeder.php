<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class MetalTraderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\MetalTrader::factory()->createOne([
            'name' => 'بردیا',
            'api_key' => 'YSVMCzVVUWdxIry2Te5sCohhFGmJpFYj',
            'phone' => '9382126008',
            'balance' => 0,
        ]);
    }
}
