<?php

namespace Tests\Feature\Api\V1;

use App\Enums\GradingStatus;
use App\Jobs\GradeSubmission;
use App\Models\Assignment;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\GradingRun;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class SubmissionControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_unauthenticated_submission_endpoints_return_401(): void
    {
        $url = '/api/v1/courses/1/assignments/1/submissions';

        $this->getJson($url)->assertUnauthorized();
        $this->getJson($url.'/1')->assertUnauthorized();
        $this->postJson($url, [])->assertUnauthorized();
    }

    public function test_creation_persists_submission_run_and_database_job_atomically(): void
    {
        [$assignment, $student] = $this->enrolledAssignment();

        $response = $this->actingAs($student)->postJson($this->url($assignment), [
            'repository_url' => '  HTTPS://GitHub.com/Example/Calculator.git/  ',
            'student_id' => 99999, 'attempt_number' => 999, 'status' => 'passed', 'commit_sha' => 'fake',
        ], ['Idempotency-Key' => (string) Str::uuid()]);

        $response->assertCreated()->assertJsonPath('data.repository_url', 'https://github.com/Example/Calculator')
            ->assertJsonPath('data.student.id', $student->id)->assertJsonPath('data.attempt_number', 1)
            ->assertJsonPath('data.status', 'queued')->assertJsonPath('data.commit_sha', null)
            ->assertJsonPath('data.grading_result.status', 'queued')->assertJsonPath('data.grade', null)
            ->assertJsonMissingPath('data.idempotency_key')->assertJsonMissingPath('data.grading_result.runner_metadata');
        $this->assertDatabaseHas('submissions', ['id' => $response->json('data.id'), 'student_id' => $student->id, 'status' => 'queued']);
        $this->assertDatabaseHas('grading_runs', ['submission_id' => $response->json('data.id'), 'run_number' => 1, 'status' => 'queued']);
        $this->assertDatabaseHas('jobs', ['queue' => 'grading', 'attempts' => 0]);
        $this->assertDatabaseCount('jobs', 1);
    }

    public function test_replaying_same_key_returns_existing_attempt_without_duplicate_dispatch_even_after_deadline(): void
    {
        $this->freezeTime();
        [$assignment, $student] = $this->enrolledAssignment(['due_at' => now()->addHour()]);
        $headers = ['Idempotency-Key' => (string) Str::uuid()];

        $first = $this->actingAs($student)->postJson($this->url($assignment), ['repository_url' => 'https://github.com/example/repo.git'], $headers);
        $this->travel(2)->hours();
        $response = $this->postJson($this->url($assignment), ['repository_url' => 'https://github.com/example/repo/'], $headers);

        $first->assertCreated();
        $response->assertOk()->assertJsonPath('data.id', $first->json('data.id'));
        $this->assertDatabaseCount('submissions', 1);
        $this->assertDatabaseCount('grading_runs', 1);
        $this->assertDatabaseCount('jobs', 1);
    }

    public function test_same_key_with_different_repository_returns_409(): void
    {
        [$assignment, $student] = $this->enrolledAssignment();
        $headers = ['Idempotency-Key' => (string) Str::uuid()];
        $this->actingAs($student)->postJson($this->url($assignment), ['repository_url' => 'https://github.com/example/first'], $headers)->assertCreated();

        $this->postJson($this->url($assignment), ['repository_url' => 'https://github.com/example/second'], $headers)
            ->assertConflict()->assertJsonPath('message', 'This idempotency key was already used for a different repository URL.');
        $this->assertDatabaseCount('submissions', 1);
        $this->assertDatabaseCount('jobs', 1);
    }

    public function test_new_keys_create_numbered_attempts_and_preserve_history(): void
    {
        $this->freezeTime();
        [$assignment, $student] = $this->enrolledAssignment();
        $url = $this->url($assignment);

        $first = $this->actingAs($student)->postJson($url, ['repository_url' => 'https://gitlab.com/example/repo'], ['Idempotency-Key' => (string) Str::uuid()]);
        $second = $this->postJson($url, ['repository_url' => 'https://gitlab.com/example/repo'], ['Idempotency-Key' => (string) Str::uuid()]);

        $first->assertCreated()->assertJsonPath('data.attempt_number', 1);
        $second->assertCreated()->assertJsonPath('data.attempt_number', 2);
        $this->getJson($url)->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('data.0.id', $second->json('data.id'));
        $this->assertDatabaseCount('submissions', 2);
        $this->assertDatabaseCount('grading_runs', 2);
        $this->assertDatabaseCount('jobs', 2);
    }

    #[DataProvider('invalidRepositoryUrls')]
    public function test_invalid_repository_returns_422_without_dispatch(mixed $repositoryUrl): void
    {
        [$assignment, $student] = $this->enrolledAssignment();
        Queue::fake([GradeSubmission::class]);

        $this->actingAs($student)->postJson($this->url($assignment), ['repository_url' => $repositoryUrl], ['Idempotency-Key' => (string) Str::uuid()])
            ->assertUnprocessable()->assertJsonValidationErrors('repository_url');
        $this->assertDatabaseCount('submissions', 0);
        Queue::assertNothingPushed();
    }

    /** @return array<string, array{mixed}> */
    public static function invalidRepositoryUrls(): array
    {
        return [
            'missing' => [null], 'array' => [[]], 'empty' => [''],
            'http' => ['http://github.com/owner/repo'],
            'ssh' => ['git@github.com:owner/repo.git'],
            'file' => ['file:///etc/passwd'],
            'git protocol' => ['git://github.com/owner/repo'],
            'local address' => ['https://127.0.0.1/owner/repo'],
            'unknown host' => ['https://example.com/owner/repo'],
            'host suffix' => ['https://github.com.evil.test/owner/repo'],
            'userinfo' => ['https://token@github.com/owner/repo'],
            'password' => ['https://user:password@github.com/owner/repo'],
            'port' => ['https://github.com:443/owner/repo'],
            'query' => ['https://github.com/owner/repo?ref=main'],
            'fragment' => ['https://github.com/owner/repo#main'],
            'extra path' => ['https://github.com/owner/repo/tree/main'],
            'subgroups' => ['https://gitlab.com/group/subgroup/repo'],
            'encoded slash' => ['https://github.com/owner/a%2Frepo'],
            'encoded traversal' => ['https://github.com/%2e%2e/repo'],
            'traversal' => ['https://github.com/../repo'],
            'dot repository' => ['https://github.com/owner/.'],
            'empty repository after suffix' => ['https://github.com/owner/.git'],
            'backslash' => ['https://github.com/owner\\repo'],
            'control character' => ["https://github.com/own\ner/repo"],
            'oversized' => ['https://github.com/owner/'.str_repeat('a', 2048)],
        ];
    }

    public function test_idempotency_header_is_required_and_cannot_be_supplied_in_body(): void
    {
        [$assignment, $student] = $this->enrolledAssignment();

        $this->actingAs($student)->postJson($this->url($assignment), [
            'repository_url' => 'https://github.com/owner/repo', 'idempotency_key' => (string) Str::uuid(),
        ])->assertUnprocessable()->assertJsonValidationErrors('idempotency_key');
        $this->assertDatabaseCount('jobs', 0);
        $this->assertDatabaseCount('submissions', 0);
    }

    public function test_invalid_idempotency_key_returns_422(): void
    {
        [$assignment, $student] = $this->enrolledAssignment();

        $this->actingAs($student)->postJson($this->url($assignment), ['repository_url' => 'https://github.com/owner/repo'], ['Idempotency-Key' => 'not-a-uuid'])
            ->assertUnprocessable()->assertJsonValidationErrors('idempotency_key');
        $this->assertDatabaseCount('submissions', 0);
    }

    #[DataProvider('closedWindows')]
    public function test_closed_submission_window_returns_422(string $reason): void
    {
        $this->freezeTime();
        [$assignment, $student] = $this->enrolledAssignment();
        match ($reason) {
            'future' => $assignment->update(['opens_at' => now()->addHour()]),
            'late' => $assignment->update(['opens_at' => now()->subDays(2), 'due_at' => now()->subDay()]),
            'assignment archived' => $assignment->gradeItem->update(['archived_at' => now()]),
            'course archived' => $assignment->gradeItem->course->update(['archived_at' => now()]),
        };

        $this->actingAs($student)->postJson($this->url($assignment), ['repository_url' => 'https://github.com/owner/repo'], ['Idempotency-Key' => (string) Str::uuid()])
            ->assertUnprocessable()->assertJsonPath('errors.assignment.0', 'This assignment is not currently open for submissions.');
        $this->assertDatabaseCount('submissions', 0);
        $this->assertDatabaseCount('jobs', 0);
    }

    /** @return array<string, array{string}> */
    public static function closedWindows(): array
    {
        return ['future' => ['future'], 'late' => ['late'], 'assignment archived' => ['assignment archived'], 'course archived' => ['course archived']];
    }

    #[DataProvider('windowBoundaries')]
    public function test_submission_is_allowed_at_exact_window_boundaries(string $field): void
    {
        $this->travelTo('2026-11-19T12:00:00Z');
        [$assignment, $student] = $this->enrolledAssignment(['opens_at' => now()->subDay(), 'due_at' => now()->addDay()]);
        $assignment->update([$field => now()]);

        $this->actingAs($student)->postJson($this->url($assignment), ['repository_url' => 'https://bitbucket.org/owner/repo'], ['Idempotency-Key' => (string) Str::uuid()])
            ->assertCreated();
        $this->assertDatabaseCount('jobs', 1);
    }

    public function test_same_key_is_scoped_to_each_student(): void
    {
        [$assignment, $student] = $this->enrolledAssignment();
        $otherStudent = User::factory()->student()->create();
        Enrollment::factory()->for($assignment->gradeItem->course)->for($otherStudent, 'student')->create();
        $headers = ['Idempotency-Key' => (string) Str::uuid()];

        $this->actingAs($student)->postJson($this->url($assignment), ['repository_url' => 'https://github.com/owner/repo'], $headers)
            ->assertCreated()->assertJsonPath('data.attempt_number', 1);
        $this->actingAs($otherStudent)->postJson($this->url($assignment), ['repository_url' => 'https://github.com/other/repo'], $headers)
            ->assertCreated()->assertJsonPath('data.attempt_number', 1);
        $this->assertDatabaseCount('jobs', 2);
        $this->assertDatabaseCount('submissions', 2);
    }

    /** @return array<string, array{string}> */
    public static function windowBoundaries(): array
    {
        return ['opens' => ['opens_at'], 'due' => ['due_at']];
    }

    public function test_only_student_owns_their_list_and_teacher_sees_all_submissions(): void
    {
        [$assignment, $student] = $this->enrolledAssignment();
        $own = Submission::factory()->for($assignment)->for($student, 'student')->create();
        $other = Submission::factory()->for($assignment)->create();
        $url = $this->url($assignment);

        $this->actingAs($student)->getJson($url)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $own->id);
        $this->getJson($url.'/'.$other->id)->assertForbidden();
        $this->actingAs($assignment->gradeItem->course->teacher)->getJson($url)->assertOk()->assertJsonCount(2, 'data');
        $this->getJson($url.'/'.$other->id)->assertOk();
    }

    #[DataProvider('gradingStatuses')]
    public function test_detail_exposes_each_status_with_feedback_and_grade(GradingStatus $status): void
    {
        [$assignment, $student] = $this->enrolledAssignment();
        $submission = Submission::factory()->for($assignment)->for($student, 'student')->create(['status' => $status]);
        GradingRun::factory()->for($submission)->create([
            'status' => $status, 'checks' => [['name' => 'go test', 'status' => $status->value]],
            'stdout' => 'technical output', 'runner_metadata' => ['internal_path' => '/private/workspace'],
        ]);
        $grade = Grade::factory()->forSubmission($submission)->create(['score' => '10.00', 'feedback' => 'Reviewed']);

        $this->actingAs($student)->getJson($this->url($assignment).'/'.$submission->id)
            ->assertOk()->assertJsonPath('data.status', $status->value)
            ->assertJsonPath('data.grading_result.status', $status->value)
            ->assertJsonPath('data.grading_result.stdout', 'technical output')
            ->assertJsonPath('data.grading_result.checks.0.status', $status->value)
            ->assertJsonPath('data.grade.id', $grade->id)->assertJsonPath('data.grade.score', '10.00')
            ->assertJsonPath('data.grade.feedback', 'Reviewed')->assertJsonCount(1, 'data.grading_runs')
            ->assertJsonMissingPath('data.grading_result.runner_metadata')->assertJsonMissing(['internal_path' => '/private/workspace']);
    }

    /** @return array<string, array{GradingStatus}> */
    public static function gradingStatuses(): array
    {
        return array_combine(array_map(fn (GradingStatus $status): string => $status->value, GradingStatus::cases()),
            array_map(fn (GradingStatus $status): array => [$status], GradingStatus::cases()));
    }

    public function test_latest_grading_run_uses_run_number(): void
    {
        [$assignment, $student] = $this->enrolledAssignment();
        $submission = Submission::factory()->for($assignment)->for($student, 'student')->create();
        $latest = GradingRun::factory()->for($submission)->create(['run_number' => 2]);
        GradingRun::factory()->for($submission)->create(['run_number' => 1]);

        $this->actingAs($student)->getJson($this->url($assignment).'/'.$submission->id)->assertOk()
            ->assertJsonPath('data.grading_result.id', $latest->id)->assertJsonCount(2, 'data.grading_runs');
    }

    public function test_removed_student_keeps_own_submission_history_but_cannot_submit(): void
    {
        [$assignment, $student] = $this->enrolledAssignment();
        $submission = Submission::factory()->for($assignment)->for($student, 'student')->create();
        Enrollment::query()->where('student_id', $student->id)->delete();
        $url = $this->url($assignment);

        $this->actingAs($student)->getJson($url)->assertOk()->assertJsonCount(1, 'data');
        $this->getJson($url.'/'.$submission->id)->assertOk();
        $this->postJson($url, ['repository_url' => 'https://github.com/owner/repo'], ['Idempotency-Key' => (string) Str::uuid()])->assertForbidden();
    }

    public function test_other_users_cannot_list_view_or_create_submissions(): void
    {
        [$assignment, $student] = $this->enrolledAssignment();
        $submission = Submission::factory()->for($assignment)->for($student, 'student')->create();
        $url = $this->url($assignment);
        $outsider = User::factory()->student()->create();

        $this->actingAs($outsider)->getJson($url)->assertForbidden();
        $this->getJson($url.'/'.$submission->id)->assertForbidden();
        $this->postJson($url, [], ['Idempotency-Key' => (string) Str::uuid()])->assertForbidden();
        $this->actingAs(User::factory()->teacher()->create())->getJson($url)->assertForbidden();
        $this->getJson($url.'/'.$submission->id)->assertForbidden();
        $this->actingAs($assignment->gradeItem->course->teacher)->postJson($url, [])->assertForbidden();
    }

    public function test_cross_assignment_or_course_submission_returns_404(): void
    {
        [$assignment, $student] = $this->enrolledAssignment();
        $otherSubmission = Submission::factory()->create();
        $wrongCourse = Course::factory()->create();

        $this->actingAs($student)->getJson($this->url($assignment).'/'.$otherSubmission->id)->assertNotFound();
        $this->getJson("/api/v1/courses/{$wrongCourse->id}/assignments/{$assignment->id}/submissions")->assertNotFound();
        $this->postJson("/api/v1/courses/{$wrongCourse->id}/assignments/{$assignment->id}/submissions", [])->assertNotFound();
    }

    public function test_status_filter_is_validated_and_preserved_in_pagination(): void
    {
        [$assignment, $student] = $this->enrolledAssignment();
        Submission::factory()->count(16)->for($assignment)->for($student, 'student')
            ->sequence(fn (Sequence $sequence): array => ['attempt_number' => $sequence->index + 1])->create(['status' => GradingStatus::Passed]);

        $this->actingAs($student)->getJson($this->url($assignment).'?status=passed')->assertOk()->assertJsonCount(15, 'data')
            ->assertJsonPath('links.next', fn (?string $url): bool => str_contains((string) $url, 'status=passed'));
        $this->getJson($this->url($assignment).'?status=failed')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson($this->url($assignment).'?status=unknown')->assertUnprocessable()->assertJsonValidationErrors('status');
    }

    public function test_dispatch_failure_rolls_back_submission_and_grading_run(): void
    {
        [$assignment, $student] = $this->enrolledAssignment();
        Bus::shouldReceive('dispatch')->once()->andThrow(new RuntimeException('Queue unavailable'));

        $this->actingAs($student)->postJson($this->url($assignment), ['repository_url' => 'https://github.com/owner/repo'], ['Idempotency-Key' => (string) Str::uuid()])
            ->assertInternalServerError();
        $this->assertDatabaseCount('submissions', 0);
        $this->assertDatabaseCount('grading_runs', 0);
        $this->assertDatabaseCount('jobs', 0);
    }

    /** @param array<string, mixed> $attributes
     * @return array{Assignment, User}
     */
    private function enrolledAssignment(array $attributes = []): array
    {
        $assignment = Assignment::factory()->create($attributes);
        $student = User::factory()->student()->create();
        Enrollment::factory()->for($assignment->gradeItem->course)->for($student, 'student')->create();

        return [$assignment, $student];
    }

    private function url(Assignment $assignment): string
    {
        return "/api/v1/courses/{$assignment->gradeItem->course_id}/assignments/{$assignment->id}/submissions";
    }
}
