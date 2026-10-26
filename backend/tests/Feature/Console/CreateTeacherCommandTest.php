<?php

namespace Tests\Feature\Console;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreateTeacherCommandTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_command_creates_a_teacher_with_a_hidden_password_prompt(): void
    {
        $this->artisan('courseci:create-teacher', [
            'email' => 'TEACHER@EXAMPLE.COM',
            '--name' => 'Ada Teacher',
        ])->expectsQuestion('Password', 'password123')
            ->expectsQuestion('Confirm password', 'password123')
            ->expectsOutput('Teacher account created for teacher@example.com.')
            ->assertExitCode(Command::SUCCESS);

        $teacher = User::query()->sole();

        $this->assertSame('Ada Teacher', $teacher->name);
        $this->assertSame('teacher@example.com', $teacher->email);
        $this->assertSame(UserRole::Teacher, $teacher->role);
        $this->assertTrue(Hash::check('password123', $teacher->password));
    }

    public function test_command_rejects_a_duplicate_email(): void
    {
        User::factory()->create(['email' => 'teacher@example.com']);

        $this->artisan('courseci:create-teacher', [
            'email' => 'TEACHER@EXAMPLE.COM',
            '--name' => 'Ada Teacher',
        ])->expectsQuestion('Password', 'password123')
            ->expectsQuestion('Confirm password', 'password123')
            ->assertExitCode(Command::FAILURE);

        $this->assertDatabaseCount('users', 1);
    }
}
