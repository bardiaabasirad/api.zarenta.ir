<?php

namespace Database\Factories;

use App\Models\MetalTrader;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ActionReason>
 */
class ApiClientFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name,
            'api_key' => function () {
                do {
                    $api_key = Str::random(32);
                } while (MetalTrader::where('api_key', $api_key)->exists());

                return $api_key;
            },
            'phone' => fake()->numerify('9#########'),
            'balance' => fake()->numberBetween(0, 100000)
        ];
    }
}
