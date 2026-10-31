<?php

namespace Tests\Feature\Policies;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class CoursePolicyTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_only_owning_teacher_can_manage_a_course(): void
    {
        $owner = User::factory()->teacher()->create();
        $otherTeacher = User::factory()->teacher()->create();
        $course = Course::factory()->for($owner, 'teacher')->create();

        $this->assertTrue(Gate::forUser($owner)->allows('create', Course::class));
        $this->assertTrue(Gate::forUser($owner)->allows('view', $course));
        $this->assertTrue(Gate::forUser($owner)->allows('update', $course));
        $this->assertTrue(Gate::forUser($owner)->allows('delete', $course));
        $this->assertFalse(Gate::forUser($otherTeacher)->allows('view', $course));
        $this->assertFalse(Gate::forUser($otherTeacher)->allows('update', $course));
    }

    public function test_only_enrolled_student_can_view_a_course(): void
    {
        $course = Course::factory()->create();
        $student = User::factory()->student()->create();
        $otherStudent = User::factory()->student()->create();
        Enrollment::factory()->for($course)->for($student, 'student')->create();

        $this->assertTrue(Gate::forUser($student)->allows('view', $course));
        $this->assertFalse(Gate::forUser($student)->allows('create', Course::class));
        $this->assertFalse(Gate::forUser($student)->allows('update', $course));
        $this->assertFalse(Gate::forUser($otherStudent)->allows('view', $course));
    }

    public function test_owner_can_view_and_archive_but_cannot_update_archived_course(): void
    {
        $owner = User::factory()->teacher()->create();
        $course = Course::factory()->for($owner, 'teacher')->create(['archived_at' => now()]);

        $this->assertTrue(Gate::forUser($owner)->allows('view', $course));
        $this->assertTrue(Gate::forUser($owner)->allows('delete', $course));
        $this->assertFalse(Gate::forUser($owner)->allows('update', $course));
    }
}
