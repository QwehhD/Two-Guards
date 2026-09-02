<?php

namespace Database\Factories;

use App\Enums\DeviceMode;
use App\Enums\DeviceStatus;
use App\Enums\PortalStatus;
use App\Models\Device;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Device>
 */
class DeviceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Portal '.$this->faker->unique()->streetName(),
            'mode' => DeviceMode::Auto,
            'last_seen_at' => now(),
            'status' => DeviceStatus::Online,
            'portal_status' => PortalStatus::Closed,
        ];
    }

    public function offline(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DeviceStatus::Offline,
            'last_seen_at' => now()->subHours(2),
        ]);
    }
}
