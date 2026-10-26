<?php

namespace Tests\Feature\Policies;

use App\Models\Assignment;
use App\Models\Course;
use App\Models\GradeItem;
use App\Models\GradingRun;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class GradingRunPolicyTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_grading_run_is_visible_only_to_submission_student_and_course_owner(): void
    {
        $owner = User::factory()->teacher()->create();
        $otherTeacher = User::factory()->teacher()->create();
        $student = User::factory()->student()->create();
        $otherStudent = User::factory()->student()->create();
        $course = Course::factory()->for($owner, 'teacher')->create();
        $gradeItem = GradeItem::factory()->for($course)->create();
        $assignment = Assignment::factory()->for($gradeItem)->create();
        $submission = Submission::factory()->for($assignment)->for($student, 'student')->create();
        $gradingRun = GradingRun::factory()->for($submission)->create();

        $this->assertTrue(Gate::forUser($student)->allows('view', $gradingRun));
        $this->assertTrue(Gate::forUser($owner)->allows('view', $gradingRun));
        $this->assertFalse(Gate::forUser($otherStudent)->allows('view', $gradingRun));
        $this->assertFalse(Gate::forUser($otherTeacher)->allows('view', $gradingRun));
        $this->assertFalse(Gate::forUser($owner)->allows('create', GradingRun::class));
        $this->assertFalse(Gate::forUser($owner)->allows('update', $gradingRun));
    }
}
