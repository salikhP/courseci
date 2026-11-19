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
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AssignmentControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_returns_401_without_authentication(): void
    {
        $this->getJson('/api/v1/courses/1/assignments')->assertUnauthorized();
        $this->getJson('/api/v1/courses/1/assignments/1')->assertUnauthorized();
        $this->postJson('/api/v1/courses/1/assignments', [])->assertUnauthorized();
        $this->patchJson('/api/v1/courses/1/assignments/1', [])->assertUnauthorized();
        $this->deleteJson('/api/v1/courses/1/assignments/1')->assertUnauthorized();
    }

    #[DataProvider('archiveFilters')]
    public function test_owner_lists_only_course_assignments_matching_status(string $query, int $count): void
    {
        $course = Course::factory()->create();
        Assignment::factory()->for(GradeItem::factory()->for($course))->create();
        Assignment::factory()->for(GradeItem::factory()->for($course)->state(['archived_at' => now()]))->create();
        GradeItem::factory()->for($course)->create(['title' => 'Manual exam']);
        Assignment::factory()->create();

        $this->actingAs($course->teacher)->getJson("/api/v1/courses/{$course->id}/assignments{$query}")
            ->assertOk()->assertJsonCount($count, 'data')->assertJsonMissing(['title' => 'Manual exam']);
    }

    /** @return array<string, array{string, int}> */
    public static function archiveFilters(): array
    {
        return [
            'active default' => ['', 1],
            'archived' => ['?status=archived', 1],
            'all' => ['?status=all', 2],
        ];
    }

    public function test_enrolled_student_views_assignment_contract(): void
    {
        $this->freezeTime();
        $course = Course::factory()->create();
        $student = User::factory()->student()->create();
        Enrollment::factory()->for($course)->for($student, 'student')->create();
        $item = GradeItem::factory()->for($course)->create([
            'title' => 'Go calculator', 'description' => 'Implement a calculator.', 'max_score' => '25.50',
        ]);
        $assignment = Assignment::factory()->for($item)->create(['opens_at' => null, 'due_at' => null]);

        $this->actingAs($student)->getJson("/api/v1/courses/{$course->id}/assignments/{$assignment->id}")
            ->assertOk()->assertExactJson(['data' => [
                'id' => $assignment->id,
                'course_id' => $course->id,
                'grade_item_id' => $item->id,
                'title' => 'Go calculator',
                'description' => 'Implement a calculator.',
                'max_score' => '25.50',
                'opens_at' => null,
                'due_at' => null,
                'archived_at' => null,
                'is_open' => true,
                'grading_requirements' => [
                    'language' => 'go',
                    'checks' => ['go build ./...', 'go vet ./...', 'go test ./...'],
                ],
                'created_at' => now()->startOfSecond()->toISOString(),
                'updated_at' => now()->startOfSecond()->toISOString(),
            ]]);
    }

    public function test_enrolled_student_lists_course_assignments(): void
    {
        $course = Course::factory()->create();
        $student = User::factory()->student()->create();
        Enrollment::factory()->for($course)->for($student, 'student')->create();
        $assignment = Assignment::factory()->for(GradeItem::factory()->for($course))->create();

        $this->actingAs($student)->getJson("/api/v1/courses/{$course->id}/assignments")
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $assignment->id);
    }

    public function test_outsider_gets_403_for_list_and_detail(): void
    {
        $course = Course::factory()->create();
        $student = User::factory()->student()->create();
        $assignment = Assignment::factory()->for(GradeItem::factory()->for($course))->create();

        $this->actingAs($student)->getJson("/api/v1/courses/{$course->id}/assignments")->assertForbidden();
        $this->actingAs($student)->getJson("/api/v1/courses/{$course->id}/assignments/{$assignment->id}")->assertForbidden();
    }

    public function test_cross_course_assignment_gets_404(): void
    {
        $course = Course::factory()->create();
        $assignment = Assignment::factory()->create();

        $this->actingAs($course->teacher)
            ->getJson("/api/v1/courses/{$course->id}/assignments/{$assignment->id}")->assertNotFound();
    }

    public function test_returns_422_for_invalid_status(): void
    {
        $course = Course::factory()->create();

        $this->actingAs($course->teacher)->getJson("/api/v1/courses/{$course->id}/assignments?status=deleted")
            ->assertUnprocessable()->assertJsonValidationErrors('status')
            ->assertJsonPath('errors.status.0', 'The selected status is invalid.');
    }

    public function test_archive_filter_is_preserved_in_pagination_links(): void
    {
        $course = Course::factory()->create();
        GradeItem::factory()->count(16)->for($course)->has(Assignment::factory())
            ->create(['archived_at' => now()]);

        $this->actingAs($course->teacher)->getJson("/api/v1/courses/{$course->id}/assignments?status=archived")
            ->assertOk()->assertJsonCount(15, 'data')
            ->assertJsonPath('links.next', fn (?string $url): bool => str_contains((string) $url, 'status=archived'));
    }

    public function test_owner_creates_assignment_and_grade_item_without_accepting_internal_fields(): void
    {
        $course = Course::factory()->create();

        $response = $this->actingAs($course->teacher)->postJson("/api/v1/courses/{$course->id}/assignments", [
            'title' => '  Calculator  ', 'description' => '  Build a calculator.  ', 'max_score' => '25.50',
            'opens_at' => '2027-01-01T10:00:00+05:00', 'due_at' => '2027-01-08T10:00:00+05:00',
            'grade_item_id' => 99999, 'course_id' => 99999, 'archived_at' => now()->toISOString(),
        ]);

        $response->assertCreated()->assertJsonPath('data.title', 'Calculator')
            ->assertJsonPath('data.description', 'Build a calculator.')
            ->assertJsonPath('data.max_score', '25.50')->assertJsonPath('data.archived_at', null)
            ->assertJsonPath('data.opens_at', '2027-01-01T05:00:00.000000Z')
            ->assertJsonPath('data.due_at', '2027-01-08T05:00:00.000000Z');
        $this->assertDatabaseHas('grade_items', [
            'id' => $response->json('data.grade_item_id'), 'course_id' => $course->id,
            'title' => 'Calculator', 'max_score' => '25.50',
        ]);
        $this->assertDatabaseHas('assignments', [
            'id' => $response->json('data.id'), 'grade_item_id' => $response->json('data.grade_item_id'),
            'opens_at' => '2027-01-01 05:00:00', 'due_at' => '2027-01-08 05:00:00',
        ]);
    }

    public function test_creating_assignment_requires_title_and_score_without_creating_partial_records(): void
    {
        $course = Course::factory()->create();

        $this->actingAs($course->teacher)->postJson("/api/v1/courses/{$course->id}/assignments", [])
            ->assertUnprocessable()->assertJsonValidationErrors(['title', 'max_score'])
            ->assertJsonPath('errors.title.0', 'The title field is required.')
            ->assertJsonPath('errors.max_score.0', 'The max score field is required.');
        $this->assertDatabaseCount('grade_items', 0);
        $this->assertDatabaseCount('assignments', 0);
    }

    /** @param array<string, mixed> $invalid */
    #[DataProvider('invalidAssignmentInputs')]
    public function test_returns_422_for_invalid_assignment_input(array $invalid, string $field): void
    {
        $course = Course::factory()->create();

        $this->actingAs($course->teacher)->postJson("/api/v1/courses/{$course->id}/assignments", [
            ...['title' => 'Calculator', 'max_score' => '100.00'], ...$invalid,
        ])->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->assertDatabaseCount('grade_items', 0);
        $this->assertDatabaseCount('assignments', 0);
    }

    /** @return array<string, array{array<string, mixed>, string}> */
    public static function invalidAssignmentInputs(): array
    {
        return [
            'blank title' => [['title' => '  '], 'title'],
            'non-string title' => [['title' => ['Calculator']], 'title'],
            'long title' => [['title' => str_repeat('a', 256)], 'title'],
            'long description' => [['description' => str_repeat('a', 10001)], 'description'],
            'non-string description' => [['description' => []], 'description'],
            'zero score' => [['max_score' => 0], 'max_score'],
            'negative score' => [['max_score' => -1], 'max_score'],
            'three decimal places' => [['max_score' => '1.001'], 'max_score'],
            'score exceeds database precision' => [['max_score' => '1000000.00'], 'max_score'],
            'non-numeric score' => [['max_score' => 'many'], 'max_score'],
            'null score' => [['max_score' => null], 'max_score'],
            'invalid opening' => [['opens_at' => 'invalid'], 'opens_at'],
            'invalid deadline' => [['due_at' => ['invalid']], 'due_at'],
            'equal dates' => [['opens_at' => '2027-01-01', 'due_at' => '2027-01-01'], 'due_at'],
            'reversed dates' => [['opens_at' => '2027-01-02', 'due_at' => '2027-01-01'], 'due_at'],
            'configurable grading' => [['grading_requirements' => ['checks' => ['echo pass']]], 'grading_requirements'],
        ];
    }

    public function test_owner_updates_assignment_and_can_clear_optional_fields(): void
    {
        $assignment = Assignment::factory()->create();
        $course = $assignment->gradeItem->course;

        $this->actingAs($course->teacher)->patchJson("/api/v1/courses/{$course->id}/assignments/{$assignment->id}", [
            'title' => '  Updated  ', 'description' => null, 'max_score' => '0.01',
            'opens_at' => null, 'due_at' => null, 'grade_item_id' => 99999, 'archived_at' => now()->toISOString(),
        ])->assertOk()->assertJsonPath('data.title', 'Updated')->assertJsonPath('data.max_score', '0.01')
            ->assertJsonPath('data.opens_at', null)->assertJsonPath('data.due_at', null)
            ->assertJsonPath('data.archived_at', null);
        $this->assertDatabaseHas('grade_items', [
            'id' => $assignment->grade_item_id, 'title' => 'Updated', 'description' => null, 'max_score' => '0.01',
        ]);
        $this->assertDatabaseHas('assignments', [
            'id' => $assignment->id, 'grade_item_id' => $assignment->grade_item_id, 'opens_at' => null, 'due_at' => null,
        ]);
    }

    #[DataProvider('partialScheduleUpdates')]
    public function test_partial_schedule_update_validates_against_saved_dates(string $field, string $date): void
    {
        $assignment = Assignment::factory()->create(['opens_at' => '2027-01-01', 'due_at' => '2027-01-08']);
        $course = $assignment->gradeItem->course;

        $this->actingAs($course->teacher)->patchJson("/api/v1/courses/{$course->id}/assignments/{$assignment->id}", [$field => $date])
            ->assertUnprocessable()->assertJsonValidationErrors('due_at')
            ->assertJsonPath('errors.due_at.0', 'The due at field must be after opens at.');
        $this->assertSame('2027-01-01', $assignment->fresh()->opens_at->toDateString());
        $this->assertSame('2027-01-08', $assignment->fresh()->due_at->toDateString());
    }

    /** @return array<string, array{string, string}> */
    public static function partialScheduleUpdates(): array
    {
        return ['opening after deadline' => ['opens_at', '2027-01-09'], 'deadline before opening' => ['due_at', '2026-12-31']];
    }

    #[DataProvider('lockedSubmissionFields')]
    public function test_submitted_assignment_rejects_changes_to_locked_fields(string $field, mixed $value): void
    {
        $assignment = Assignment::factory()->create(['opens_at' => '2027-01-01', 'due_at' => '2027-01-08']);
        $course = $assignment->gradeItem->course;
        Submission::factory()->for($assignment)->create();
        $original = $assignment->gradeItem->title;

        $this->actingAs($course->teacher)->patchJson("/api/v1/courses/{$course->id}/assignments/{$assignment->id}", [
            'title' => 'Must not persist', $field => $value,
        ])->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->assertSame($original, $assignment->gradeItem->fresh()->title);
    }

    public function test_update_rejects_invalid_fields_without_persisting_changes(): void
    {
        $assignment = Assignment::factory()->create();
        $course = $assignment->gradeItem->course;
        $original = $assignment->gradeItem->title;

        $this->actingAs($course->teacher)->patchJson("/api/v1/courses/{$course->id}/assignments/{$assignment->id}", [
            'title' => '  ', 'max_score' => '1.001',
        ])->assertUnprocessable()->assertJsonValidationErrors(['title', 'max_score'])
            ->assertJsonPath('errors.title.0', 'The title field is required.')
            ->assertJsonPath('errors.max_score.0', 'The max score field must have 0-2 decimal places.');
        $this->assertSame($original, $assignment->gradeItem->fresh()->title);
    }

    public function test_enrolled_student_cannot_write_assignments(): void
    {
        $assignment = Assignment::factory()->create();
        $course = $assignment->gradeItem->course;
        $student = User::factory()->student()->create();
        Enrollment::factory()->for($course)->for($student, 'student')->create();
        $url = "/api/v1/courses/{$course->id}/assignments";

        $this->actingAs($student)->postJson($url, ['title' => 'Forbidden', 'max_score' => 100])->assertForbidden();
        $this->patchJson("{$url}/{$assignment->id}", ['title' => 'Forbidden'])->assertForbidden();
        $this->deleteJson("{$url}/{$assignment->id}")->assertForbidden();
        $this->assertDatabaseCount('assignments', 1);
        $this->assertNull($assignment->gradeItem->fresh()->archived_at);
    }

    /** @return array<string, array{string, mixed}> */
    public static function lockedSubmissionFields(): array
    {
        return [
            'score' => ['max_score', '999999.99'],
            'opening' => ['opens_at', '2027-01-02'],
            'clear opening' => ['opens_at', null],
        ];
    }

    public function test_submitted_assignment_allows_text_and_deadline_edits_and_unchanged_locked_values(): void
    {
        $assignment = Assignment::factory()->for(GradeItem::factory()->state(['max_score' => '100.00']))
            ->create(['opens_at' => '2027-01-01T00:00:00Z', 'due_at' => '2027-01-08']);
        $course = $assignment->gradeItem->course;
        Submission::factory()->for($assignment)->create();

        $this->actingAs($course->teacher)->patchJson("/api/v1/courses/{$course->id}/assignments/{$assignment->id}", [
            'title' => 'Revised', 'description' => 'More detail', 'due_at' => '2027-01-09',
            'max_score' => 100, 'opens_at' => '2027-01-01T05:00:00+05:00',
        ])->assertOk()->assertJsonPath('data.title', 'Revised')->assertJsonPath('data.description', 'More detail')
            ->assertJsonPath('data.due_at', '2027-01-09T00:00:00.000000Z');
        $this->assertDatabaseHas('grade_items', ['id' => $assignment->grade_item_id, 'title' => 'Revised']);
        $this->assertSame('2027-01-09', $assignment->fresh()->due_at->toDateString());
        $this->assertSame('2027-01-01T00:00:00.000000Z', $assignment->fresh()->opens_at->toISOString());
    }

    public function test_archive_is_idempotent_and_preserves_submissions_and_grades(): void
    {
        $this->freezeTime();
        $assignment = Assignment::factory()->create();
        $course = $assignment->gradeItem->course;
        $submission = Submission::factory()->for($assignment)->create();
        $grade = Grade::factory()->forSubmission($submission)->create();
        $url = "/api/v1/courses/{$course->id}/assignments/{$assignment->id}";

        $this->actingAs($course->teacher)->deleteJson($url)->assertNoContent();
        $archivedAt = $assignment->gradeItem->fresh()->archived_at->toISOString();
        $this->travel(1)->hour();
        $this->deleteJson($url)->assertNoContent();

        $this->assertSame($archivedAt, $assignment->gradeItem->fresh()->archived_at->toISOString());
        $this->assertModelExists($assignment);
        $this->assertModelExists($submission);
        $this->assertModelExists($grade);
        $this->getJson($url)->assertOk()->assertJsonPath('data.is_open', false);
        $this->getJson("/api/v1/courses/{$course->id}/assignments")->assertOk()->assertJsonCount(0, 'data');
        $this->patchJson($url, ['title' => 'Changed'])->assertForbidden();
    }

    #[DataProvider('writeRoles')]
    public function test_non_owner_gets_403_for_all_assignment_writes(string $role): void
    {
        $user = User::factory()->create(['role' => $role]);
        $assignment = Assignment::factory()->create();
        $course = $assignment->gradeItem->course;
        $url = "/api/v1/courses/{$course->id}/assignments";

        $this->actingAs($user)->postJson($url, ['title' => 'Forbidden', 'max_score' => 100])->assertForbidden();
        $this->patchJson("{$url}/{$assignment->id}", ['title' => 'Forbidden'])->assertForbidden();
        $this->deleteJson("{$url}/{$assignment->id}")->assertForbidden();
        $this->assertDatabaseCount('assignments', 1);
        $this->assertNull($assignment->gradeItem->fresh()->archived_at);
    }

    /** @return array<string, array{string}> */
    public static function writeRoles(): array
    {
        return ['other teacher' => ['teacher'], 'student' => ['student']];
    }

    public function test_cross_course_writes_get_404(): void
    {
        $course = Course::factory()->create();
        $assignment = Assignment::factory()->create();
        $url = "/api/v1/courses/{$course->id}/assignments/{$assignment->id}";

        $this->actingAs($course->teacher)->patchJson($url, ['title' => 'Changed'])->assertNotFound();
        $this->deleteJson($url)->assertNotFound();
    }

    public function test_archived_course_is_readable_but_rejects_assignment_writes(): void
    {
        $course = Course::factory()->create(['archived_at' => now()]);
        $assignment = Assignment::factory()->for(GradeItem::factory()->for($course))->create();
        $url = "/api/v1/courses/{$course->id}/assignments";

        $this->actingAs($course->teacher)->getJson($url)->assertOk();
        $this->getJson("{$url}/{$assignment->id}")->assertOk()->assertJsonPath('data.is_open', false);
        $this->postJson($url, ['title' => 'Changed', 'max_score' => 100])->assertForbidden();
        $this->patchJson("{$url}/{$assignment->id}", ['title' => 'Changed'])->assertForbidden();
        $this->deleteJson("{$url}/{$assignment->id}")->assertForbidden();
    }

    #[DataProvider('submissionWindows')]
    public function test_student_sees_assignment_and_correct_submission_window(?string $opensAt, ?string $dueAt, bool $isOpen): void
    {
        $this->travelTo('2027-01-05T12:00:00Z');
        $course = Course::factory()->create();
        $student = User::factory()->student()->create();
        Enrollment::factory()->for($course)->for($student, 'student')->create();
        $assignment = Assignment::factory()->for(GradeItem::factory()->for($course))
            ->create(['opens_at' => $opensAt, 'due_at' => $dueAt]);

        $this->actingAs($student)->getJson("/api/v1/courses/{$course->id}/assignments")
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.is_open', $isOpen);
        $this->getJson("/api/v1/courses/{$course->id}/assignments/{$assignment->id}")
            ->assertOk()->assertJsonPath('data.is_open', $isOpen);
    }

    /** @return array<string, array{?string, ?string, bool}> */
    public static function submissionWindows(): array
    {
        return [
            'unrestricted' => [null, null, true],
            'upcoming' => ['2027-01-06T00:00:00Z', null, false],
            'opening boundary' => ['2027-01-05T12:00:00Z', null, true],
            'deadline boundary' => [null, '2027-01-05T12:00:00Z', true],
            'overdue' => [null, '2027-01-05T11:59:59Z', false],
        ];
    }
}
