<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Self-service account endpoints stay closed: accounts and their passwords
 * are managed only by an admin (POST/PUT /api/users). With the Fortify
 * features off, these URIs only match the SPA's GET catch-all route, so a
 * POST gets 405 Method Not Allowed.
 */
class DisabledFortifyFeaturesTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_registration_is_closed(): void
    {
        $response = $this->postJson('/register', [
            'name' => 'Orang Asing',
            'email' => 'stranger@example.test',
            'password' => 'Str4nger-Passw0rd!',
            'password_confirmation' => 'Str4nger-Passw0rd!',
        ]);

        // Before: a 201 and a karyawan account, which may approve opening
        // the gate.
        $response->assertMethodNotAllowed();
        $this->assertDatabaseMissing('users', ['email' => 'stranger@example.test']);
        $this->assertGuest();
    }

    #[DataProvider('closedAccountRecoveryEndpoints')]
    public function test_password_reset_and_email_verification_are_closed(string $uri): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->postJson($uri, ['email' => $user->email])->assertMethodNotAllowed();

        Notification::assertNothingSent();
    }

    public static function closedAccountRecoveryEndpoints(): array
    {
        return [
            'forgot password' => ['/forgot-password'],
            'reset password' => ['/reset-password'],
            'resend verification email' => ['/email/verification-notification'],
        ];
    }
}
