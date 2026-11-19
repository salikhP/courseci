<?php

namespace Tests\Feature\Policies;

use App\Models\Assignment;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\GradeItem;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class AssignmentPolicyTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_owner_can_manage_and_enrolled_student_can_only_view_assignment(): void
    {
        $owner = User::factory()->teacher()->create();
        $student = User::factory()->student()->create();
        $course = Course::factory()->for($owner, 'teacher')->create();
        $gradeItem = GradeItem::factory()->for($course)->create();
        $assignment = Assignment::factory()->for($gradeItem)->create();
        Enrollment::factory()->for($course)->for($student, 'student')->create();

        $this->assertTrue(Gate::forUser($owner)->allows('create', [Assignment::class, $gradeItem]));
        $this->assertTrue(Gate::forUser($owner)->allows('update', $assignment));
        $this->assertTrue(Gate::forUser($student)->allows('view', $assignment));
        $this->assertFalse(Gate::forUser($student)->allows('update', $assignment));
    }

    public function test_outsiders_cannot_view_or_manage_assignment(): void
    {
        $assignment = Assignment::factory()->create();
        $otherTeacher = User::factory()->teacher()->create();
        $otherStudent = User::factory()->student()->create();

        $this->assertFalse(Gate::forUser($otherTeacher)->allows('view', $assignment));
        $this->assertFalse(Gate::forUser($otherTeacher)->allows('delete', $assignment));
        $this->assertFalse(Gate::forUser($otherStudent)->allows('view', $assignment));
    }

    public function test_assignment_creation_requires_active_owned_course(): void
    {
        $course = Course::factory()->create();
        $student = User::factory()->student()->create();
        $otherTeacher = User::factory()->teacher()->create();

        $this->assertTrue(Gate::forUser($course->teacher)->allows('create', [Assignment::class, $course]));
        $this->assertFalse(Gate::forUser($student)->allows('create', [Assignment::class, $course]));
        $this->assertFalse(Gate::forUser($otherTeacher)->allows('create', [Assignment::class, $course]));
        $course->update(['archived_at' => now()]);
        $this->assertFalse(Gate::forUser($course->teacher)->allows('create', [Assignment::class, $course]));
    }

    public function test_archived_assignment_can_be_viewed_and_archived_again_but_cannot_be_edited(): void
    {
        $item = GradeItem::factory()->create(['archived_at' => now()]);
        $assignment = Assignment::factory()->for($item)->create();
        $owner = $item->course->teacher;
        $student = User::factory()->student()->create();
        Enrollment::factory()->for($item->course)->for($student, 'student')->create();

        $this->assertTrue(Gate::forUser($owner)->allows('view', $assignment));
        $this->assertTrue(Gate::forUser($student)->allows('view', $assignment));
        $this->assertTrue(Gate::forUser($owner)->allows('delete', $assignment));
        $this->assertFalse(Gate::forUser($owner)->allows('update', $assignment));
        $this->assertFalse(Gate::forUser($student)->allows('delete', $assignment));
        $this->assertFalse(Gate::forUser($owner)->allows('restore', $assignment));
        $this->assertFalse(Gate::forUser($owner)->allows('forceDelete', $assignment));
    }

    public function test_archived_course_disallows_assignment_mutations(): void
    {
        $course = Course::factory()->create(['archived_at' => now()]);
        $assignment = Assignment::factory()->for(GradeItem::factory()->for($course))->create();

        $this->assertTrue(Gate::forUser($course->teacher)->allows('view', $assignment));
        $this->assertFalse(Gate::forUser($course->teacher)->allows('update', $assignment));
        $this->assertFalse(Gate::forUser($course->teacher)->allows('delete', $assignment));
    }
}
