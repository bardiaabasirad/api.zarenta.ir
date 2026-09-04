<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PriceSource>
 */
class ReferenceChannelFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'channel_id' => fake()->unique()->slug,
            'channel_name' => fake()->unique()->name,
            'ordering' => fake()->numberBetween(1, 100),
            'open' => '07:00:00',
            'close' => '23:00:00',
            'target_phrase' => null,
            'multiplied_by' => 1,
            'reading_method' => fake()->randomElement(['method_one','method_two','method_three']),
            'work_with' => fake()->randomElement(['active','inactive']),
            'reading_from_telegram' => fake()->randomElement(['active','inactive'])
        ];
    }
}
