<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MarketPrice>
 */
class MarketPriceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ounce' => fake()->numberBetween(2200000, 3900000),
            'price' => fake()->numberBetween(2200000, 3900000),
            'emam_coin' => fake()->numberBetween(2200000, 3900000),
            'full_coin' => fake()->numberBetween(2200000, 3900000),
            'half_coin' => fake()->numberBetween(2200000, 3900000),
            'quarter_coin' => fake()->numberBetween(2200000, 3900000),
            'dollar' => fake()->numberBetween(40000, 70000),
            'euro' => fake()->numberBetween(40000, 80000),
            'derham' => fake()->numberBetween(10000, 30000),
            'read_at' => fake()->dateTime,
        ];
    }
}
