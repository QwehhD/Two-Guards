<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The signed-in user's own profile, as the SPA settings page uses it:
 * GET/PATCH/DELETE /api/profile.
 */
class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_can_be_read(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/profile');

        $response->assertOk();
        $response->assertJsonFragment(['id' => $user->id, 'email' => $user->email]);
        $response->assertJsonMissingPath('password');
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->patchJson('/api/profile', [
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $response->assertOk();
        $response->assertJsonFragment(['name' => 'Test User', 'email' => 'test@example.com']);

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->patchJson('/api/profile', [
            'name' => 'Test User',
            'email' => $user->email,
        ])->assertOk();

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_an_account_cannot_be_deleted_from_its_own_profile(): void
    {
        $user = User::factory()->create();

        // Only an admin deletes accounts, on the Akun page (DELETE /api/users/{user}).
        $response = $this->actingAs($user)->deleteJson('/api/profile', [
            'password' => 'password',
        ]);

        $response->assertMethodNotAllowed();
        $this->assertNotNull($user->fresh());
    }
}
