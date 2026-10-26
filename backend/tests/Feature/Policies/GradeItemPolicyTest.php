<?php

namespace Tests\Feature\Policies;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\GradeItem;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class GradeItemPolicyTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_owner_can_manage_and_enrolled_student_can_only_view_grade_item(): void
    {
        $owner = User::factory()->teacher()->create();
        $student = User::factory()->student()->create();
        $course = Course::factory()->for($owner, 'teacher')->create();
        $gradeItem = GradeItem::factory()->for($course)->create();
        Enrollment::factory()->for($course)->for($student, 'student')->create();

        $this->assertTrue(Gate::forUser($owner)->allows('create', [GradeItem::class, $course]));
        $this->assertTrue(Gate::forUser($owner)->allows('update', $gradeItem));
        $this->assertTrue(Gate::forUser($student)->allows('view', $gradeItem));
        $this->assertFalse(Gate::forUser($student)->allows('update', $gradeItem));
    }

    public function test_outsiders_cannot_view_or_manage_grade_item(): void
    {
        $gradeItem = GradeItem::factory()->create();
        $otherTeacher = User::factory()->teacher()->create();
        $otherStudent = User::factory()->student()->create();

        $this->assertFalse(Gate::forUser($otherTeacher)->allows('view', $gradeItem));
        $this->assertFalse(Gate::forUser($otherTeacher)->allows('delete', $gradeItem));
        $this->assertFalse(Gate::forUser($otherStudent)->allows('view', $gradeItem));
    }
}
