<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ActionReason>
 */
class OptionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => fake()->randomElement(['increase_inventory_count','decrease_inventory_count']),
            'value' => fake()->text(255)
        ];
    }

    public function increaseInventoryCount(): static
    {
        return $this->state(fn (array $attributes) => [
            'key' => 'increase_inventory_count',
        ]);
    }

    public function decreaseInventoryCount(): static
    {
        return $this->state(fn (array $attributes) => [
            'key' => 'decrease_inventory_count',
        ]);
    }

    public function banUser(): static
    {
        return $this->state(fn (array $attributes) => [
            'key' => 'ban_user',
        ]);
    }
}
