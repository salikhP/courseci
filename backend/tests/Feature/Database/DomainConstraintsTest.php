<?php

namespace Tests\Feature\Database;

use App\Enums\UserRole;
use App\Models\Assignment;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\GradeItem;
use App\Models\GradingRun;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DomainConstraintsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_users_default_to_the_student_role(): void
    {
        $user = User::query()->create([
            'name' => 'Default Student',
            'email' => 'default-student@example.com',
            'password' => 'password',
        ]);

        $this->assertSame(UserRole::Student, $user->refresh()->role);
    }

    public function test_database_rejects_an_invalid_user_role(): void
    {
        $this->expectException(QueryException::class);

        DB::table('users')->insert([
            'name' => 'Invalid Role',
            'email' => 'invalid-role@example.com',
            'password' => 'password',
            'role' => 'administrator',
        ]);
    }

    public function test_database_rejects_duplicate_course_enrollment(): void
    {
        $enrollment = Enrollment::factory()->create();

        $this->expectException(QueryException::class);

        Enrollment::factory()
            ->for($enrollment->course)
            ->for($enrollment->student, 'student')
            ->create();
    }

    public function test_database_rejects_a_nonpositive_grade_item_score(): void
    {
        $this->expectException(QueryException::class);

        GradeItem::factory()->create(['max_score' => '0.00']);
    }

    public function test_database_rejects_an_assignment_due_before_it_opens(): void
    {
        $this->expectException(QueryException::class);

        Assignment::factory()->create([
            'opens_at' => now()->addDay(),
            'due_at' => now(),
        ]);
    }

    public function test_database_allows_only_one_assignment_per_grade_item(): void
    {
        $assignment = Assignment::factory()->create();

        $this->expectException(QueryException::class);

        Assignment::factory()->for($assignment->gradeItem)->create();
    }

    public function test_database_rejects_a_nonpositive_submission_attempt(): void
    {
        $this->expectException(QueryException::class);

        Submission::factory()->create(['attempt_number' => 0]);
    }

    public function test_database_rejects_an_invalid_submission_status(): void
    {
        $assignment = Assignment::factory()->create();
        $student = User::factory()->student()->create();

        $this->expectException(QueryException::class);

        DB::table('submissions')->insert([
            'assignment_id' => $assignment->id,
            'student_id' => $student->id,
            'repository_url' => 'https://github.com/student/coursework',
            'attempt_number' => 1,
            'status' => 'unknown',
            'submitted_at' => now(),
        ]);
    }

    public function test_database_rejects_a_nonpositive_grading_run_number(): void
    {
        $this->expectException(QueryException::class);

        GradingRun::factory()->create(['run_number' => 0]);
    }

    public function test_database_rejects_unbounded_grading_output(): void
    {
        $this->expectException(QueryException::class);

        GradingRun::factory()->create(['stdout' => str_repeat('a', 65536)]);
    }

    public function test_database_rejects_a_negative_grade(): void
    {
        $this->expectException(QueryException::class);

        Grade::factory()->create(['score' => '-0.01']);
    }

    public function test_database_allows_only_one_grade_per_grade_item_and_student(): void
    {
        $grade = Grade::factory()->create();

        $this->expectException(QueryException::class);

        Grade::factory()
            ->for($grade->gradeItem)
            ->for($grade->student, 'student')
            ->create();
    }
}
