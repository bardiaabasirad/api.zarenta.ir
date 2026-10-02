<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class PhysicalAddressSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\PhysicalAddress::factory()->createOne(
            [
                'city_id' => 1275,
                'title' => 'طلای زرنتا',
                'address' => 'استان لرستان، دلفان، خیابان امام خمینی (ره)، روبروی بانک ملی، طلای زرنتا',
            ],
        );
    }
}
