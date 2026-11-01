<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'full_name' => fake()->name(),
            'phone' => fake()->numerify('9#########'),
            'national_code' => fake()->numerify('##########'),
            'password' => static::$password ??= Hash::make('password'),
            'status' => fake()->randomElement(['active', 'inactive', 'initial', 'incomplete']),
            'remember_token' => Str::random(10),
            'last_login_at' => fake()->dateTime(),
            'last_login_ip' => fake()->ipv4(),
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

    public function admin(): static
    {
        return $this->afterCreating(function (User $user) {
            if (! $user->hasRole('admin')) {
                $user->addRole('admin');
            }
        })->state(fn (array $attributes) => [
            'status' => 'active',
        ]);
    }
}
