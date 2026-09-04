<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class RateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\SelectedMetalPrice::factory()->createOne([
            'reference_channel_id' => 3,
        ]);
    }
}
