<?php

namespace Tests\Feature\Api\V1;

use App\Models\Assignment;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\GradeItem;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class EnrollmentControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_returns_401_when_enrollment_list_is_unauthenticated(): void
    {
        $course = Course::factory()->create();

        $this->getJson("/api/v1/courses/{$course->id}/enrollments")
            ->assertUnauthorized();
    }

    public function test_owner_lists_only_course_enrollments(): void
    {
        $teacher = User::factory()->teacher()->create();
        $course = Course::factory()->for($teacher, 'teacher')->create();
        $otherCourse = Course::factory()->for($teacher, 'teacher')->create();
        $enrollment = Enrollment::factory()->for($course)->create();
        Enrollment::factory()->for($otherCourse)->create();

        $this->actingAs($teacher)
            ->getJson("/api/v1/courses/{$course->id}/enrollments")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $enrollment->id)
            ->assertJsonPath('data.0.student.id', $enrollment->student_id)
            ->assertJsonStructure(['links', 'meta']);
    }

    public function test_student_cannot_list_course_enrollments(): void
    {
        $student = User::factory()->student()->create();
        $course = Course::factory()->create();
        Enrollment::factory()->for($course)->for($student, 'student')->create();

        $this->actingAs($student)
            ->getJson("/api/v1/courses/{$course->id}/enrollments")
            ->assertForbidden();
    }

    public function test_owner_enrolls_student_by_normalized_email(): void
    {
        $teacher = User::factory()->teacher()->create();
        $student = User::factory()->student()->create(['email' => 'student@example.com']);
        $course = Course::factory()->for($teacher, 'teacher')->create();

        $response = $this->actingAs($teacher)
            ->postJson("/api/v1/courses/{$course->id}/enrollments", [
                'email' => '  STUDENT@EXAMPLE.COM  ',
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.student.id', $student->id)
            ->assertJsonPath('data.student.email', 'student@example.com');

        $this->assertDatabaseHas('enrollments', [
            'course_id' => $course->id,
            'student_id' => $student->id,
        ]);
    }

    public function test_returns_422_when_student_email_is_unknown(): void
    {
        $teacher = User::factory()->teacher()->create();
        $course = Course::factory()->for($teacher, 'teacher')->create();

        $this->actingAs($teacher)
            ->postJson("/api/v1/courses/{$course->id}/enrollments", [
                'email' => 'missing@example.com',
            ])->assertUnprocessable()
            ->assertJsonValidationErrors('email')
            ->assertJsonPath('errors.email.0', 'No student account was found for this email address.');

        $this->assertDatabaseCount('enrollments', 0);
    }

    public function test_returns_422_when_student_email_is_invalid(): void
    {
        $teacher = User::factory()->teacher()->create();
        $course = Course::factory()->for($teacher, 'teacher')->create();

        $this->actingAs($teacher)
            ->postJson("/api/v1/courses/{$course->id}/enrollments", [
                'email' => 'not-an-email',
            ])->assertUnprocessable()
            ->assertJsonPath('errors.email.0', 'The email field must be a valid email address.');

        $this->assertDatabaseCount('enrollments', 0);
    }

    public function test_returns_422_when_email_belongs_to_teacher(): void
    {
        $owner = User::factory()->teacher()->create();
        $teacher = User::factory()->teacher()->create(['email' => 'teacher@example.com']);
        $course = Course::factory()->for($owner, 'teacher')->create();

        $this->actingAs($owner)
            ->postJson("/api/v1/courses/{$course->id}/enrollments", [
                'email' => $teacher->email,
            ])->assertUnprocessable()
            ->assertJsonPath('errors.email.0', 'No student account was found for this email address.');

        $this->assertDatabaseCount('enrollments', 0);
    }

    public function test_returns_422_for_duplicate_enrollment(): void
    {
        $teacher = User::factory()->teacher()->create();
        $student = User::factory()->student()->create();
        $course = Course::factory()->for($teacher, 'teacher')->create();
        Enrollment::factory()->for($course)->for($student, 'student')->create();

        $this->actingAs($teacher)
            ->postJson("/api/v1/courses/{$course->id}/enrollments", [
                'email' => $student->email,
            ])->assertUnprocessable()
            ->assertJsonPath('errors.email.0', 'This student is already enrolled in the course.');

        $this->assertDatabaseCount('enrollments', 1);
    }

    public function test_other_teacher_cannot_enroll_student(): void
    {
        $otherTeacher = User::factory()->teacher()->create();
        $student = User::factory()->student()->create();
        $course = Course::factory()->create();

        $this->actingAs($otherTeacher)
            ->postJson("/api/v1/courses/{$course->id}/enrollments", [
                'email' => $student->email,
            ])->assertForbidden();

        $this->assertDatabaseCount('enrollments', 0);
    }

    public function test_archived_course_rejects_new_enrollment(): void
    {
        $teacher = User::factory()->teacher()->create();
        $student = User::factory()->student()->create();
        $course = Course::factory()->for($teacher, 'teacher')->create(['archived_at' => now()]);

        $this->actingAs($teacher)
            ->postJson("/api/v1/courses/{$course->id}/enrollments", [
                'email' => $student->email,
            ])->assertForbidden();

        $this->assertDatabaseCount('enrollments', 0);
    }

    public function test_owner_removes_enrollment_without_deleting_submission_or_grade(): void
    {
        $teacher = User::factory()->teacher()->create();
        $student = User::factory()->student()->create();
        $course = Course::factory()->for($teacher, 'teacher')->create();
        $enrollment = Enrollment::factory()->for($course)->for($student, 'student')->create();
        $gradeItem = GradeItem::factory()->for($course)->create();
        $assignment = Assignment::factory()->for($gradeItem)->create();
        $submission = Submission::factory()->for($assignment)->for($student, 'student')->create();
        $grade = Grade::factory()
            ->forSubmission($submission)
            ->for($teacher, 'grader')
            ->create();

        $this->actingAs($teacher)
            ->deleteJson("/api/v1/courses/{$course->id}/enrollments/{$enrollment->id}")
            ->assertNoContent();

        $this->assertModelMissing($enrollment);
        $this->assertModelExists($submission);
        $this->assertModelExists($grade);
    }

    public function test_enrollment_from_another_course_returns_404(): void
    {
        $teacher = User::factory()->teacher()->create();
        $course = Course::factory()->for($teacher, 'teacher')->create();
        $otherCourse = Course::factory()->for($teacher, 'teacher')->create();
        $enrollment = Enrollment::factory()->for($otherCourse)->create();

        $this->actingAs($teacher)
            ->deleteJson("/api/v1/courses/{$course->id}/enrollments/{$enrollment->id}")
            ->assertNotFound();

        $this->assertModelExists($enrollment);
    }

    public function test_archived_course_rejects_enrollment_removal(): void
    {
        $teacher = User::factory()->teacher()->create();
        $course = Course::factory()->for($teacher, 'teacher')->create(['archived_at' => now()]);
        $enrollment = Enrollment::factory()->for($course)->create();

        $this->actingAs($teacher)
            ->deleteJson("/api/v1/courses/{$course->id}/enrollments/{$enrollment->id}")
            ->assertForbidden();

        $this->assertModelExists($enrollment);
    }
}
