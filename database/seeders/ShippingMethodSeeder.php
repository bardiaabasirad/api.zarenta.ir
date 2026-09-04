<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ShippingMethodSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\ShippingMethod::factory()->createOne(
            [
                'title' => 'ارسال از طریق پیک',
            ],
        );
        \App\Models\ShippingMethod::factory()->createOne(
            [
                'title' => 'ارسال از طریق پست',
            ],
        );
        \App\Models\ShippingMethod::factory()->createOne(
            [
                'title' => 'ارسال از طریق تیپاکس',
            ],
        );
    }
}
