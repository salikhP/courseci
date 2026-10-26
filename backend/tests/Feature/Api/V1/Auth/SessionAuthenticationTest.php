<?php

namespace Tests\Feature\Api\V1\Auth;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class SessionAuthenticationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_csrf_cookie_endpoint_is_available(): void
    {
        $this->get('/sanctum/csrf-cookie')
            ->assertNoContent()
            ->assertCookie('XSRF-TOKEN');
    }

    public function test_user_can_start_a_spa_session(): void
    {
        User::factory()->student()->create([
            'email' => 'student@example.com',
            'password' => 'password123',
        ]);

        $this->fromSpa()->postJson('/api/v1/auth/session', [
            'email' => 'STUDENT@EXAMPLE.COM',
            'password' => 'password123',
        ])->assertOk()
            ->assertJsonPath('data.email', 'student@example.com')
            ->assertJsonPath('data.role', UserRole::Student->value);

        $this->assertAuthenticated();
    }

    public function test_invalid_session_credentials_return_the_same_error(): void
    {
        User::factory()->create([
            'email' => 'student@example.com',
            'password' => 'password123',
        ]);

        $unknownUser = $this->fromSpa()->postJson('/api/v1/auth/session', [
            'email' => 'missing@example.com',
            'password' => 'password123',
        ]);
        $wrongPassword = $this->fromSpa()->postJson('/api/v1/auth/session', [
            'email' => 'student@example.com',
            'password' => 'wrong-password',
        ]);

        $unknownUser->assertUnprocessable();
        $wrongPassword->assertUnprocessable();
        $this->assertSame(
            $unknownUser->json('errors.email.0'),
            $wrongPassword->json('errors.email.0'),
        );
        $this->assertSame(__('auth.failed'), $unknownUser->json('errors.email.0'));
    }

    public function test_authenticated_user_can_end_a_spa_session(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $this->fromSpa()->deleteJson('/api/v1/auth/session')->assertNoContent();

        $this->assertGuest();
        $this->getJson('/api/v1/user')->assertUnauthorized();
    }

    public function test_guest_cannot_end_a_spa_session(): void
    {
        $this->fromSpa()->deleteJson('/api/v1/auth/session')->assertUnauthorized();
    }

    public function test_session_login_is_rate_limited_by_email_and_ip(): void
    {
        foreach (range(1, 5) as $attempt) {
            $this->fromSpa()->postJson('/api/v1/auth/session', [
                'email' => 'missing@example.com',
                'password' => "wrong-password-{$attempt}",
            ])->assertUnprocessable();
        }

        $this->fromSpa()->postJson('/api/v1/auth/session', [
            'email' => 'MISSING@EXAMPLE.COM',
            'password' => 'wrong-password-6',
        ])->assertTooManyRequests();
    }

    private function fromSpa(): static
    {
        return $this->withHeader('Origin', 'http://localhost:5173');
    }
}
