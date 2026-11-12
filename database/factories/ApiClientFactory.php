<?php

namespace Database\Factories;

use App\Models\MetalTrader;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MetalTrader>
 */
class ApiClientFactory extends Factory
{
    protected $model = MetalTrader::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name,
            'phone' => fake()->numerify('9#########'),
            'status' => 'active',
        ];
    }
}
