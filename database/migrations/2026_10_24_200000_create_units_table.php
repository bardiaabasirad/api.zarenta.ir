<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('units', function (Blueprint $table) {
            $table->string('id', 20)->primary();
            $table->string('name', 50);
            $table->decimal('multiplier_to_gram', 16, 6)->default(0);
            $table->boolean('is_weight')->default(true);
            $table->timestamps();
        });

        $now = now();

        DB::table('units')->insert([
            [
                'id' => 'gram',
                'name' => 'گرم',
                'multiplier_to_gram' => 1,
                'is_weight' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 'mazane',
                'name' => 'مظنه',
                'multiplier_to_gram' => 4.331802,
                'is_weight' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 'ounce',
                'name' => 'انس',
                'multiplier_to_gram' => 31.103477,
                'is_weight' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 'kilogram',
                'name' => 'کیلوگرم',
                'multiplier_to_gram' => 1000,
                'is_weight' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 'ton',
                'name' => 'تن',
                'multiplier_to_gram' => 1000000,
                'is_weight' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 'count',
                'name' => 'عدد',
                'multiplier_to_gram' => 0,
                'is_weight' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('units');
    }
};
