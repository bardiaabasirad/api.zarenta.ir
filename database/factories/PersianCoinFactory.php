<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PersianCoin>
 */
class PersianCoinFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            /** from 0.5 to 10
             ** the precision is 3
             ** ex: 2.25 or 8.505 or 4.2
            **/
            'weight' => fake()->randomFloat(3, 0.5, 10),
            'percentage_profit' => fake()->numberBetween(1, 4),
            'tomans_profit' => fake()->numberBetween(0, 50000),
            'percentage_wage' => fake()->numberBetween(0, 4),
            'tomans_wage' => fake()->numberBetween(0, 50000),
            'status' => fake()->randomElement(['active','inactive']),
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
        ]);
    }

    public function inActive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'inactive',
        ]);
    }
}
