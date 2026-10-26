<?php

namespace Tests\Feature\Policies;

use App\Models\Assignment;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\GradeItem;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class SubmissionPolicyTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_only_enrolled_student_can_create_a_submission(): void
    {
        $owner = User::factory()->teacher()->create();
        $student = User::factory()->student()->create();
        $otherStudent = User::factory()->student()->create();
        $course = Course::factory()->for($owner, 'teacher')->create();
        $gradeItem = GradeItem::factory()->for($course)->create();
        $assignment = Assignment::factory()->for($gradeItem)->create();
        Enrollment::factory()->for($course)->for($student, 'student')->create();

        $this->assertTrue(Gate::forUser($student)->allows('create', [Submission::class, $assignment]));
        $this->assertFalse(Gate::forUser($otherStudent)->allows('create', [Submission::class, $assignment]));
        $this->assertFalse(Gate::forUser($owner)->allows('create', [Submission::class, $assignment]));
    }

    public function test_submission_is_visible_only_to_its_student_and_course_owner(): void
    {
        $owner = User::factory()->teacher()->create();
        $otherTeacher = User::factory()->teacher()->create();
        $student = User::factory()->student()->create();
        $otherStudent = User::factory()->student()->create();
        $course = Course::factory()->for($owner, 'teacher')->create();
        $gradeItem = GradeItem::factory()->for($course)->create();
        $assignment = Assignment::factory()->for($gradeItem)->create();
        $submission = Submission::factory()->for($assignment)->for($student, 'student')->create();

        $this->assertTrue(Gate::forUser($student)->allows('view', $submission));
        $this->assertTrue(Gate::forUser($owner)->allows('view', $submission));
        $this->assertFalse(Gate::forUser($otherStudent)->allows('view', $submission));
        $this->assertFalse(Gate::forUser($otherTeacher)->allows('view', $submission));
        $this->assertFalse(Gate::forUser($student)->allows('update', $submission));
    }
}
