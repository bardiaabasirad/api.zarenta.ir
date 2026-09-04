<?php

namespace Database\Seeders;

use App\Models\SubscriptionFeature;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SubscriptionFeaturesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        SubscriptionFeature::create([
            'name' => 'دریافت مظنه',
            'slug' => 'get_price',
        ]);

        SubscriptionFeature::create([
            'name' => 'ثبت سفارش',
            'slug' => 'order_create',
        ]);

        SubscriptionFeature::create([
            'name' => 'دریافت قیمت مارکت',
            'slug' => 'get_market',
        ]);

        SubscriptionFeature::create([
            'name' => 'دسترسی به سرویس استعلام',
            'slug' => 'inquiry',
        ]);
    }
}
