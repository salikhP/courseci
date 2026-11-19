<?php

namespace Tests\Feature\Jobs;

use App\Enums\GradingStatus;
use App\Jobs\GradeSubmission;
use App\Models\GradingRun;
use App\Models\Submission;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class GradeSubmissionTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_processing_without_runner_reports_system_error_and_never_executes_code(): void
    {
        $this->freezeTime();
        $submission = Submission::factory()->create();
        $run = GradingRun::factory()->for($submission)->create(['status' => GradingStatus::Queued, 'finished_at' => null]);
        Process::fake();

        (new GradeSubmission($submission->id))->handle();

        $this->assertSame(GradingStatus::SystemError, $submission->fresh()->status);
        $this->assertDatabaseHas('grading_runs', ['id' => $run->id, 'status' => 'system_error', 'error_code' => 'runner_unavailable']);
        $this->assertSame(now()->startOfSecond()->toISOString(), $run->fresh()->finished_at->toISOString());
        Process::assertNothingRan();
    }

    public function test_duplicate_delivery_does_not_overwrite_completed_results(): void
    {
        $submission = Submission::factory()->create(['status' => GradingStatus::Passed]);
        $run = GradingRun::factory()->for($submission)->create(['status' => GradingStatus::Passed]);
        Process::fake();

        (new GradeSubmission($submission->id))->handle();
        (new GradeSubmission($submission->id))->handle();

        $this->assertSame(GradingStatus::Passed, $submission->fresh()->status);
        $this->assertSame(GradingStatus::Passed, $run->fresh()->status);
        $this->assertDatabaseCount('grading_runs', 1);
        Process::assertNothingRan();
    }
}
