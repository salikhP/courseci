<?php

namespace Tests\Feature\Policies;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class EnrollmentPolicyTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_only_course_owner_can_manage_enrollments(): void
    {
        $owner = User::factory()->teacher()->create();
        $otherTeacher = User::factory()->teacher()->create();
        $student = User::factory()->student()->create();
        $course = Course::factory()->for($owner, 'teacher')->create();
        $enrollment = Enrollment::factory()->for($course)->for($student, 'student')->create();

        $this->assertTrue(Gate::forUser($owner)->allows('viewAny', [Enrollment::class, $course]));
        $this->assertTrue(Gate::forUser($owner)->allows('create', [Enrollment::class, $course]));
        $this->assertTrue(Gate::forUser($owner)->allows('view', $enrollment));
        $this->assertTrue(Gate::forUser($owner)->allows('delete', $enrollment));
        $this->assertFalse(Gate::forUser($otherTeacher)->allows('viewAny', [Enrollment::class, $course]));
        $this->assertFalse(Gate::forUser($otherTeacher)->allows('create', [Enrollment::class, $course]));
        $this->assertFalse(Gate::forUser($otherTeacher)->allows('view', $enrollment));
    }

    public function test_student_can_only_view_their_own_enrollment(): void
    {
        $course = Course::factory()->create();
        $student = User::factory()->student()->create();
        $otherStudent = User::factory()->student()->create();
        $enrollment = Enrollment::factory()->for($course)->for($student, 'student')->create();

        $this->assertTrue(Gate::forUser($student)->allows('view', $enrollment));
        $this->assertFalse(Gate::forUser($student)->allows('delete', $enrollment));
        $this->assertFalse(Gate::forUser($otherStudent)->allows('view', $enrollment));
    }

    public function test_owner_can_view_but_cannot_change_enrollments_in_archived_course(): void
    {
        $owner = User::factory()->teacher()->create();
        $course = Course::factory()->for($owner, 'teacher')->create(['archived_at' => now()]);
        $enrollment = Enrollment::factory()->for($course)->create();

        $this->assertTrue(Gate::forUser($owner)->allows('viewAny', [Enrollment::class, $course]));
        $this->assertTrue(Gate::forUser($owner)->allows('view', $enrollment));
        $this->assertFalse(Gate::forUser($owner)->allows('create', [Enrollment::class, $course]));
        $this->assertFalse(Gate::forUser($owner)->allows('delete', $enrollment));
    }
}
