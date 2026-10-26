<?php

namespace Tests\Feature\Policies;

use App\Models\Course;
use App\Models\Grade;
use App\Models\GradeItem;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class GradePolicyTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_course_owner_can_manage_a_grade_and_graded_student_can_view_it(): void
    {
        $owner = User::factory()->teacher()->create();
        $student = User::factory()->student()->create();
        $course = Course::factory()->for($owner, 'teacher')->create();
        $gradeItem = GradeItem::factory()->for($course)->create();
        $grade = Grade::factory()
            ->for($gradeItem)
            ->for($student, 'student')
            ->for($owner, 'grader')
            ->create();

        $this->assertTrue(Gate::forUser($owner)->allows('create', [Grade::class, $gradeItem]));
        $this->assertTrue(Gate::forUser($owner)->allows('update', $grade));
        $this->assertTrue(Gate::forUser($owner)->allows('delete', $grade));
        $this->assertTrue(Gate::forUser($student)->allows('view', $grade));
        $this->assertFalse(Gate::forUser($student)->allows('update', $grade));
    }

    public function test_other_users_cannot_view_or_manage_a_grade(): void
    {
        $grade = Grade::factory()->create();
        $otherTeacher = User::factory()->teacher()->create();
        $otherStudent = User::factory()->student()->create();

        $this->assertFalse(Gate::forUser($otherTeacher)->allows('view', $grade));
        $this->assertFalse(Gate::forUser($otherTeacher)->allows('update', $grade));
        $this->assertFalse(Gate::forUser($otherStudent)->allows('view', $grade));
    }
}
