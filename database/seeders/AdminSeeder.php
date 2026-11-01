<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user = User::factory()->active()->createOne([
            'full_name' => 'بردیا عباسی راد',
            'phone' => '9382126008',
            'national_code' => '4200031108',
            'password' => Hash::make('#%Zeinab2348'),
        ]);

        $user->addRole('admin');
        $user->addRole('programmer');
    }
}
