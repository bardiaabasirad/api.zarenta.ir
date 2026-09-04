<?php

namespace Database\Factories;

use App\Models\Color;
use App\Models\Product;
use App\Models\Variety;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Variety>
 */
class VarietyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'color_id' => Color::factory(),
            'barcode' => fake()->unique()->numberBetween(100000, 999999),
            'weight' => fake()->randomFloat(3, 0.5, 5),
            'size' => fake()->numberBetween(1, 1000),
            'count' => fake()->numberBetween(1,10),
            'gold_price' => fake()->numberBetween(2500000,3900000),
            'percentage_profit' => fake()->numberBetween(1,10),
            'tomans_profit' => fake()->numberBetween(0, 100000),
            'percentage_discount' => fake()->numberBetween(1,10),
            'tomans_discount' => fake()->numberBetween(0, 100000),
            'percentage_buy_wage' => fake()->numberBetween(1,10),
            'tomans_buy_wage' => fake()->numberBetween(0, 100000),
            'percentage_sell_wage' => fake()->numberBetween(1,10),
            'tomans_sell_wage' => fake()->numberBetween(0, 100000),
        ];
    }
}
