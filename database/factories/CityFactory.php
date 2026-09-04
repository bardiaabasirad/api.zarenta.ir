<?php

namespace Database\Factories;

use App\Models\Admin;
use App\Models\Brand;
use App\Models\Color;
use App\Models\Product;
use App\Models\ProductType;
use App\Models\Province;
use App\Models\SizeUnit;
use App\Models\Variety;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product>
 */
class CityFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'province_id' => Province::factory()->create()->id,
            'name' => fake()->city,
        ];
    }
}
