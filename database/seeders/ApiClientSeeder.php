<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ApiClientSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\MetalTrader::factory()->createOne([
            'name' => 'بردیا',
            'api_key' => 'YSVMCzVVUWdxIry2Te5sCohhFGmJpFYj',
            'phone' => '9165797066',
            'balance' => 100000,
        ]);
    }
}
