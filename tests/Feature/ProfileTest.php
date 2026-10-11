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

    public function test_user_can_delete_their_account(): void
    {
        $user = User::factory()->create();

        // Deleting also ends the session, which only exists on a stateful
        // (SPA) request — so send it the way the SPA does, from a domain
        // Sanctum treats as stateful.
        config(['sanctum.stateful' => ['localhost']]);

        $response = $this->actingAs($user)
            ->withHeader('Referer', 'http://localhost/settings/profile')
            ->deleteJson('/api/profile', [
                'password' => 'password',
            ]);

        $response->assertNoContent();
        // The session login (web guard) is what ProfileController ends;
        // during the request auth:sanctum made sanctum the default guard.
        $this->assertGuest('web');
        $this->assertNull($user->fresh());
    }

    public function test_correct_password_must_be_provided_to_delete_account(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->deleteJson('/api/profile', [
            'password' => 'wrong-password',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('password');
        $this->assertNotNull($user->fresh());
    }
}
