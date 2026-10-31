<?php

namespace Tests\Feature\Api\V1;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CourseControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_returns_401_when_course_list_is_unauthenticated(): void
    {
        $this->getJson('/api/v1/courses')->assertUnauthorized();
    }

    /**
     * @param  list<string>  $visibleTitles
     */
    #[DataProvider('courseStatusFilters')]
    public function test_teacher_lists_only_owned_courses_matching_status(
        ?string $status,
        array $visibleTitles,
    ): void {
        $teacher = User::factory()->teacher()->create();
        $otherTeacher = User::factory()->teacher()->create();
        Course::factory()->for($teacher, 'teacher')->create(['title' => 'Active course']);
        Course::factory()->for($teacher, 'teacher')->create([
            'title' => 'Archived course',
            'archived_at' => now(),
        ]);
        Course::factory()->for($otherTeacher, 'teacher')->create(['title' => 'Other course']);
        $url = '/api/v1/courses'.($status === null ? '' : "?status={$status}");

        $response = $this->actingAs($teacher)->getJson($url);

        $response->assertOk()->assertJsonCount(count($visibleTitles), 'data');

        foreach ($visibleTitles as $title) {
            $response->assertJsonFragment(['title' => $title]);
        }

        $response->assertJsonMissing(['title' => 'Other course']);
    }

    /** @return array<string, array{?string, list<string>}> */
    public static function courseStatusFilters(): array
    {
        return [
            'active by default' => [null, ['Active course']],
            'archived' => ['archived', ['Archived course']],
            'all' => ['all', ['Active course', 'Archived course']],
        ];
    }

    public function test_student_lists_only_enrolled_active_courses(): void
    {
        $student = User::factory()->student()->create();
        $activeCourse = Course::factory()->create(['title' => 'Enrolled course']);
        $archivedCourse = Course::factory()->create([
            'title' => 'Archived enrolled course',
            'archived_at' => now(),
        ]);
        Course::factory()->create(['title' => 'Unrelated course']);
        Enrollment::factory()->for($activeCourse)->for($student, 'student')->create();
        Enrollment::factory()->for($archivedCourse)->for($student, 'student')->create();

        $this->actingAs($student)
            ->getJson('/api/v1/courses')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $activeCourse->id)
            ->assertJsonPath('data.0.teacher.id', $activeCourse->teacher_id)
            ->assertJsonMissing(['title' => 'Unrelated course']);
    }

    public function test_returns_422_for_invalid_course_status(): void
    {
        $teacher = User::factory()->teacher()->create();

        $this->actingAs($teacher)
            ->getJson('/api/v1/courses?status=deleted')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status')
            ->assertJsonPath('errors.status.0', 'The selected status is invalid.');
    }

    public function test_course_status_filter_is_preserved_in_pagination_links(): void
    {
        $teacher = User::factory()->teacher()->create();
        Course::factory()->count(16)->for($teacher, 'teacher')->create([
            'archived_at' => now(),
        ]);

        $this->actingAs($teacher)
            ->getJson('/api/v1/courses?status=archived')
            ->assertOk()
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath(
                'links.next',
                fn (?string $url): bool => str_contains((string) $url, 'status=archived'),
            );
    }

    public function test_teacher_creates_course_and_cannot_assign_another_owner(): void
    {
        $teacher = User::factory()->teacher()->create();
        $otherTeacher = User::factory()->teacher()->create();

        $response = $this->actingAs($teacher)->postJson('/api/v1/courses', [
            'title' => '  Go Fundamentals  ',
            'description' => '  Learn Go through practice.  ',
            'teacher_id' => $otherTeacher->id,
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.title', 'Go Fundamentals')
            ->assertJsonPath('data.description', 'Learn Go through practice.')
            ->assertJsonPath('data.teacher.id', $teacher->id);

        $this->assertDatabaseHas('courses', [
            'teacher_id' => $teacher->id,
            'title' => 'Go Fundamentals',
            'description' => 'Learn Go through practice.',
        ]);
    }

    public function test_student_cannot_create_course(): void
    {
        $student = User::factory()->student()->create();

        $this->actingAs($student)->postJson('/api/v1/courses', [
            'title' => 'Unauthorized course',
        ])->assertForbidden();

        $this->assertDatabaseCount('courses', 0);
    }

    public function test_course_creation_requires_title(): void
    {
        $teacher = User::factory()->teacher()->create();

        $this->actingAs($teacher)
            ->postJson('/api/v1/courses', ['description' => 'Missing title'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('title')
            ->assertJsonPath('errors.title.0', 'The title field is required.');

        $this->assertDatabaseCount('courses', 0);
    }

    public function test_course_creation_rejects_oversized_fields(): void
    {
        $teacher = User::factory()->teacher()->create();

        $this->actingAs($teacher)->postJson('/api/v1/courses', [
            'title' => Str::repeat('a', 256),
            'description' => Str::repeat('b', 10001),
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['title', 'description'])
            ->assertJsonPath('errors.title.0', 'The title field must not be greater than 255 characters.')
            ->assertJsonPath('errors.description.0', 'The description field must not be greater than 10000 characters.');

        $this->assertDatabaseCount('courses', 0);
    }

    public function test_enrolled_student_views_course(): void
    {
        $student = User::factory()->student()->create();
        $course = Course::factory()->create();
        Enrollment::factory()->for($course)->for($student, 'student')->create();

        $this->actingAs($student)
            ->getJson("/api/v1/courses/{$course->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $course->id)
            ->assertJsonPath('data.teacher.id', $course->teacher_id);
    }

    public function test_unrelated_teacher_cannot_view_course(): void
    {
        $otherTeacher = User::factory()->teacher()->create();
        $course = Course::factory()->create();

        $this->actingAs($otherTeacher)
            ->getJson("/api/v1/courses/{$course->id}")
            ->assertForbidden();
    }

    public function test_owner_updates_active_course_without_changing_owner(): void
    {
        $teacher = User::factory()->teacher()->create();
        $otherTeacher = User::factory()->teacher()->create();
        $course = Course::factory()->for($teacher, 'teacher')->create();

        $this->actingAs($teacher)->patchJson("/api/v1/courses/{$course->id}", [
            'title' => '  Updated course  ',
            'description' => null,
            'teacher_id' => $otherTeacher->id,
        ])->assertOk()
            ->assertJsonPath('data.title', 'Updated course')
            ->assertJsonPath('data.description', null)
            ->assertJsonPath('data.teacher.id', $teacher->id);

        $this->assertDatabaseHas('courses', [
            'id' => $course->id,
            'teacher_id' => $teacher->id,
            'title' => 'Updated course',
            'description' => null,
        ]);
    }

    public function test_archived_course_cannot_be_updated(): void
    {
        $teacher = User::factory()->teacher()->create();
        $course = Course::factory()->for($teacher, 'teacher')->create([
            'title' => 'Archived course',
            'archived_at' => now(),
        ]);

        $this->actingAs($teacher)
            ->patchJson("/api/v1/courses/{$course->id}", ['title' => 'Changed'])
            ->assertForbidden();

        $this->assertDatabaseHas('courses', [
            'id' => $course->id,
            'title' => 'Archived course',
        ]);
    }

    public function test_course_update_rejects_empty_title(): void
    {
        $teacher = User::factory()->teacher()->create();
        $course = Course::factory()->for($teacher, 'teacher')->create(['title' => 'Original title']);

        $this->actingAs($teacher)
            ->patchJson("/api/v1/courses/{$course->id}", ['title' => '  '])
            ->assertUnprocessable()
            ->assertJsonPath('errors.title.0', 'The title field is required.');

        $this->assertDatabaseHas('courses', [
            'id' => $course->id,
            'title' => 'Original title',
        ]);
    }

    public function test_owner_archives_course_without_deleting_it(): void
    {
        $this->freezeTime();
        $teacher = User::factory()->teacher()->create();
        $course = Course::factory()->for($teacher, 'teacher')->create();

        $this->actingAs($teacher)
            ->deleteJson("/api/v1/courses/{$course->id}")
            ->assertNoContent();

        $course->refresh();
        $archivedAt = $course->archived_at?->toISOString();

        $this->actingAs($teacher)
            ->deleteJson("/api/v1/courses/{$course->id}")
            ->assertNoContent();

        $this->assertSame(now()->startOfSecond()->toISOString(), $archivedAt);
        $this->assertSame($archivedAt, $course->fresh()->archived_at?->toISOString());
        $this->assertModelExists($course);
    }

    public function test_student_cannot_archive_course(): void
    {
        $student = User::factory()->student()->create();
        $course = Course::factory()->create();
        Enrollment::factory()->for($course)->for($student, 'student')->create();

        $this->actingAs($student)
            ->deleteJson("/api/v1/courses/{$course->id}")
            ->assertForbidden();

        $this->assertNull($course->fresh()->archived_at);
    }
}
