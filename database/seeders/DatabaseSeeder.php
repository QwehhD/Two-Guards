<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\AccessLog;
use App\Models\Device;
use App\Models\RfidCard;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $admin = User::factory()->create([
            'name' => 'Admin Portal',
            'email' => 'admin@example.com',
            'role' => UserRole::Admin,
        ]);

        $karyawan1 = User::factory()->create([
            'name' => 'Karyawan Satu',
            'email' => 'karyawan1@example.com',
            'role' => UserRole::Karyawan,
        ]);

        $karyawan2 = User::factory()->create([
            'name' => 'Karyawan Dua',
            'email' => 'karyawan2@example.com',
            'role' => UserRole::Karyawan,
        ]);

        $device = Device::factory()->create([
            'name' => 'Portal Gerbang Utama',
        ]);

        $cards = RfidCard::factory(5)->create([
            'created_by' => $admin->id,
        ]);

        // rfid_card_id must be passed explicitly per row, otherwise the
        // factory's default 'rfid_card_id' => RfidCard::factory() spawns a
        // brand-new card (and its own created_by user) for every log instead
        // of reusing the 5 cards seeded above.
        for ($i = 0; $i < 5; $i++) {
            AccessLog::factory()->create([
                'device_id' => $device->id,
                'rfid_card_id' => $cards->random()->id,
            ]);
        }

        for ($i = 0; $i < 2; $i++) {
            AccessLog::factory()->denied()->create([
                'device_id' => $device->id,
                'rfid_card_id' => $cards->random()->id,
            ]);
        }

        AccessLog::factory(3)->unknownCard()->create([
            'device_id' => $device->id,
        ]);
    }
}
