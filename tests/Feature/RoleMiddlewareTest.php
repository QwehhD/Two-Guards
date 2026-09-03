<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_access_admin_only_route(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $response = $this->actingAs($admin)->getJson('/api/users');

        $response->assertOk();
    }

    public function test_karyawan_is_forbidden_from_admin_only_route(): void
    {
        $karyawan = User::factory()->create(['role' => UserRole::Karyawan]);

        $response = $this->actingAs($karyawan)->getJson('/api/users');

        $response->assertForbidden();
        $response->assertJson(['message' => 'Forbidden']);
    }

    public function test_admin_can_access_any_role_route(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $response = $this->actingAs($admin)->getJson('/api/access-logs');

        $response->assertOk();
    }

    public function test_karyawan_can_access_any_role_route(): void
    {
        $karyawan = User::factory()->create(['role' => UserRole::Karyawan]);

        $response = $this->actingAs($karyawan)->getJson('/api/access-logs');

        $response->assertOk();
    }

    public function test_unauthenticated_user_is_rejected_from_admin_only_route(): void
    {
        $response = $this->getJson('/api/users');

        $response->assertUnauthorized();
    }

    public function test_unauthenticated_user_is_rejected_from_any_role_route(): void
    {
        $response = $this->getJson('/api/access-logs');

        $response->assertUnauthorized();
    }
}
