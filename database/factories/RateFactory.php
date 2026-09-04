<?php

namespace Database\Factories;

use App\Models\PriceSource;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SelectedMetalPrice>
 */
class RateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $buy = fake()->numberBetween(27000000, 30000000);
        $sell = round($buy * 1.1);

        return [
            'reference_channel_id' => PriceSource::factory(),
            'buy' => $buy,
            'sell' => $sell,
            'time' => Carbon::now()->format('Y-m-d H:i:s'),
        ];
    }
}
