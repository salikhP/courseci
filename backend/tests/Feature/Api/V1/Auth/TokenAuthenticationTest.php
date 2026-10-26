<?php

namespace Tests\Feature\Api\V1\Auth;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TokenAuthenticationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_user_can_create_and_use_a_mobile_token(): void
    {
        $user = User::factory()->student()->create([
            'email' => 'student@example.com',
            'password' => 'password123',
        ]);

        $response = $this->postJson('/api/v1/auth/tokens', [
            'email' => 'STUDENT@EXAMPLE.COM',
            'password' => 'password123',
            'device_name' => 'Pixel 10',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.user.role', UserRole::Student->value);

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'name' => 'Pixel 10',
            'abilities' => '["mobile"]',
        ]);

        $this->withToken($response->json('data.token'))
            ->getJson('/api/v1/user')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id);
    }

    public function test_invalid_token_credentials_do_not_create_a_token(): void
    {
        User::factory()->create([
            'email' => 'student@example.com',
            'password' => 'password123',
        ]);

        $this->postJson('/api/v1/auth/tokens', [
            'email' => 'student@example.com',
            'password' => 'wrong-password',
            'device_name' => 'Pixel 10',
        ])->assertUnprocessable()
            ->assertJsonPath('errors.email.0', __('auth.failed'));

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_device_name_is_required_for_a_mobile_token(): void
    {
        $this->postJson('/api/v1/auth/tokens', [
            'email' => 'student@example.com',
            'password' => 'password123',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('device_name')
            ->assertJsonPath('errors.device_name.0', 'The device name field is required.');
    }

    public function test_mobile_token_can_revoke_itself(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('Pixel 10', ['mobile']);

        $this->withToken($token->plainTextToken)
            ->deleteJson('/api/v1/auth/tokens/current')
            ->assertNoContent();

        $this->assertDatabaseCount('personal_access_tokens', 0);

        $this->app['auth']->forgetGuards();

        $this->withToken($token->plainTextToken)
            ->getJson('/api/v1/user')
            ->assertUnauthorized();
    }

    public function test_spa_session_cannot_use_mobile_token_revocation_endpoint(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $this->deleteJson('/api/v1/auth/tokens/current')->assertForbidden();
    }
}
