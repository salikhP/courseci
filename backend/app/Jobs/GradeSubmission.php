<?php

namespace App\Jobs;

use App\Enums\GradingStatus;
use App\Models\Submission;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class GradeSubmission implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 30;

    /**
     * Create a new job instance.
     */
    public function __construct(public int $submissionId)
    {
        $this->onConnection('database');
        $this->onQueue('grading');
        // PostgreSQL stores the job and submission in the same transaction.
        $this->beforeCommit();
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        DB::transaction(function (): void {
            $submission = Submission::query()->whereKey($this->submissionId)->lockForUpdate()->first();

            if ($submission === null || $submission->status !== GradingStatus::Queued) {
                return;
            }

            // Phase 8 replaces this unavailable-runner result with isolated execution.
            $submission->gradingRuns()->where('run_number', 1)->update([
                'status' => GradingStatus::SystemError,
                'error_code' => 'runner_unavailable',
                'finished_at' => now(),
            ]);
            $submission->update(['status' => GradingStatus::SystemError]);
        });
    }
}
