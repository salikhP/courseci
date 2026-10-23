<?php

namespace Tests\Feature\Models;

use App\Enums\GradingStatus;
use App\Enums\UserRole;
use App\Models\Assignment;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\GradeItem;
use App\Models\GradingRun;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class DomainModelsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_repository_assignment_models_form_the_expected_relationships(): void
    {
        $teacher = User::factory()->teacher()->create();
        $student = User::factory()->student()->create();
        $course = Course::factory()->for($teacher, 'teacher')->create();
        $enrollment = Enrollment::factory()
            ->for($course)
            ->for($student, 'student')
            ->create();
        $gradeItem = GradeItem::factory()->for($course)->create();
        $assignment = Assignment::factory()->for($gradeItem)->create();
        $submission = Submission::factory()
            ->for($assignment)
            ->for($student, 'student')
            ->create(['status' => GradingStatus::Passed]);
        $run = GradingRun::factory()->for($submission)->create([
            'status' => GradingStatus::Passed,
            'checks' => [['name' => 'go test', 'status' => 'passed']],
        ]);
        $grade = Grade::factory()
            ->forSubmission($submission)
            ->for($teacher, 'grader')
            ->create();

        $this->assertSame(UserRole::Teacher, $teacher->role);
        $this->assertSame(UserRole::Student, $student->role);
        $this->assertTrue($course->teacher->is($teacher));
        $this->assertTrue($enrollment->course->is($course));
        $this->assertTrue($enrollment->student->is($student));
        $this->assertTrue($gradeItem->course->is($course));
        $this->assertTrue($gradeItem->assignment->is($assignment));
        $this->assertTrue($assignment->gradeItem->is($gradeItem));
        $this->assertSame('100.00', $gradeItem->max_score);
        $this->assertSame(GradingStatus::Passed, $submission->status);
        $this->assertTrue($submission->student->is($student));
        $this->assertTrue($run->submission->is($submission));
        $this->assertSame([['name' => 'go test', 'status' => 'passed']], $run->checks);
        $this->assertTrue($grade->gradeItem->is($gradeItem));
        $this->assertTrue($grade->student->is($student));
        $this->assertTrue($grade->submission->is($submission));
        $this->assertTrue($grade->grader->is($teacher));
        $this->assertTrue($assignment->grades->contains($grade));
        $this->assertTrue($course->students->contains($student));
        $this->assertTrue($course->assignments->contains($assignment));
    }

    public function test_manual_grade_models_form_the_expected_relationships(): void
    {
        $teacher = User::factory()->teacher()->create();
        $student = User::factory()->student()->create();
        $course = Course::factory()->for($teacher, 'teacher')->create();
        $gradeItem = GradeItem::factory()->for($course)->create([
            'title' => 'Midterm Exam',
            'max_score' => '30.00',
        ]);
        $grade = Grade::factory()
            ->for($gradeItem)
            ->for($student, 'student')
            ->for($teacher, 'grader')
            ->create(['score' => '25.00']);

        $this->assertTrue($gradeItem->course->is($course));
        $this->assertNull($gradeItem->assignment);
        $this->assertTrue($grade->gradeItem->is($gradeItem));
        $this->assertTrue($grade->student->is($student));
        $this->assertTrue($grade->grader->is($teacher));
        $this->assertNull($grade->submission);
        $this->assertSame('25.00', $grade->score);
    }
}
