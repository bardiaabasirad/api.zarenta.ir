<?php

namespace Database\Seeders;

use App\Models\MarketPrice;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class MarketPriceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        MarketPrice::factory(2500)->create();
    }
}
