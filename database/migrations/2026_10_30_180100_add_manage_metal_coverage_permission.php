<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $exists = DB::table('permissions')
            ->where('name', 'manage-metal-coverage')
            ->exists();

        if (!$exists) {
            DB::table('permissions')->insert([
                'name' => 'manage-metal-coverage',
                'display_name' => 'مدیریت پوشش خزانه فلز',
                'description' => 'manage metal treasury coverage',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('permissions')->where('name', 'manage-metal-coverage')->delete();
    }
};
