<?php

namespace Database\Seeders;

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
            'phone' => '9382126008',
            'national_code' => '4200031108',
            'password' => Hash::make('#%Zeinab2348'),
        ]);
    }
}
