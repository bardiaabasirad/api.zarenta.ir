<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
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
                'city_id' => 939,
                'title' => 'طلای ژیک',
                'address' => 'استان قم، شهر قم، خیابان توحید، بعد از کوچه ۹، پاساژ علی ابن موسی الرضا، طبقه زیر همکف، انتهای سالن',
            ],
        );
    }
}
