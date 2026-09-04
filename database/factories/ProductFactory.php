<?php

namespace Database\Factories;

use App\Models\Admin;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'created_by' => Admin::factory(),
            'title' => fake()->text(100),
            'description' => fake()->text,
            'gold_carat' => 750,
            'status' => fake()->randomElement(['active','inactive']),
            'credit_payment' => fake()->randomElement(['active','inactive']),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Product $product) {
            \App\Models\Variety::factory(3)->create([
                'product_id' => $product->id
            ]);

            $product->images()->createMany([
                [
                    'image' => '/public/products/6621deb586eab.jpg',
                ],
                [
                    'image' => '/public/products/66212fa381bfa.jpg',
                ],
            ]);
        });
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

    public function activeCreditPayment(): static
    {
        return $this->state(fn (array $attributes) => [
            'credit_payment' => 'active',
        ]);
    }

    public function inActiveCreditPayment(): static
    {
        return $this->state(fn (array $attributes) => [
            'credit_payment' => 'inactive',
        ]);
    }
}
