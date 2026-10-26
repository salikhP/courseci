<?php

namespace Tests\Feature\Api\V1\Auth;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_student_can_register_without_being_logged_in(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => '  Ada Student  ',
            'email' => 'ADA.STUDENT@EXAMPLE.COM',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => UserRole::Teacher->value,
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.name', 'Ada Student')
            ->assertJsonPath('data.email', 'ada.student@example.com')
            ->assertJsonPath('data.role', UserRole::Student->value);

        $this->assertGuest();

        $user = User::query()->sole();

        $this->assertSame(UserRole::Student, $user->role);
        $this->assertTrue(Hash::check('password123', $user->password));
    }

    public function test_registration_requires_all_fields(): void
    {
        $this->postJson('/api/v1/auth/register')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email', 'password'])
            ->assertJsonPath('errors.name.0', 'The name field is required.');

        $this->assertDatabaseCount('users', 0);
    }

    public function test_registration_rejects_a_duplicate_email(): void
    {
        User::factory()->create(['email' => 'student@example.com']);

        $this->postJson('/api/v1/auth/register', [
            'name' => 'Another Student',
            'email' => 'STUDENT@EXAMPLE.COM',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        $this->assertDatabaseCount('users', 1);
    }

    public function test_registration_requires_password_confirmation(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'name' => 'Ada Student',
            'email' => 'student@example.com',
            'password' => 'password123',
            'password_confirmation' => 'different-password',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('password')
            ->assertJsonPath('errors.password.0', 'The password field confirmation does not match.');
    }

    public function test_registration_is_rate_limited_by_ip_address(): void
    {
        foreach (range(1, 5) as $attempt) {
            $this->postJson('/api/v1/auth/register', [
                'name' => "Student {$attempt}",
                'email' => "student{$attempt}@example.com",
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ])->assertCreated();
        }

        $this->postJson('/api/v1/auth/register', [
            'name' => 'Rate Limited Student',
            'email' => 'limited@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertTooManyRequests();
    }
}
