<?php

namespace Database\Factories;

use App\Enums\RfidCardStatus;
use App\Models\RfidCard;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RfidCard>
 */
class RfidCardFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uid' => strtoupper($this->faker->unique()->bothify('########')),
            'owner_name' => $this->faker->name(),
            'status' => RfidCardStatus::Active,
            'created_by' => User::factory(),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => RfidCardStatus::Inactive,
        ]);
    }
}
