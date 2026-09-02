<?php

namespace Database\Factories;

use App\Enums\AccessLogMode;
use App\Enums\AccessLogStatus;
use App\Models\AccessLog;
use App\Models\Device;
use App\Models\RfidCard;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AccessLog>
 */
class AccessLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'device_id' => Device::factory(),
            'rfid_card_id' => RfidCard::factory(),
            // Depends on rfid_card_id above being resolved first, so the raw
            // scan mirrors the linked card's real uid.
            'scanned_uid' => fn (array $attributes) => RfidCard::find($attributes['rfid_card_id'])->uid,
            'mode' => AccessLogMode::Auto,
            'status' => AccessLogStatus::Approved,
            'processed_by' => null,
            'scanned_at' => now(),
            'processed_at' => now(),
        ];
    }

    /**
     * Simulate a scan from a card that isn't registered in the system.
     */
    public function unknownCard(): static
    {
        return $this->state(fn (array $attributes) => [
            'rfid_card_id' => null,
            'scanned_uid' => strtoupper($this->faker->unique()->bothify('########')),
            'status' => AccessLogStatus::Denied,
            'processed_by' => null,
        ]);
    }

    public function denied(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AccessLogStatus::Denied,
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AccessLogStatus::Pending,
            'mode' => AccessLogMode::Manual,
            'processed_by' => null,
            'processed_at' => null,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AccessLogStatus::Expired,
            'mode' => AccessLogMode::Manual,
            'processed_by' => null,
        ]);
    }

    /**
     * A scan that a staff member manually approved/denied.
     */
    public function manual(): static
    {
        return $this->state(fn (array $attributes) => [
            'mode' => AccessLogMode::Manual,
            'processed_by' => User::factory(),
        ]);
    }
}
