<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\Admin::factory()->active()->createOne([
            'full_name' => 'بردیا عباسی راد',
            'phone' => '9165797066',
            'national_code' => '4200031108',
            'password' => Hash::make('12345678'),
        ]);
    }
}
