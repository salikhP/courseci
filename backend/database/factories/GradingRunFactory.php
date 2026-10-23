<?php

namespace Database\Factories;

use App\Enums\GradingStatus;
use App\Models\GradingRun;
use App\Models\Submission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GradingRun>
 */
class GradingRunFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'submission_id' => Submission::factory(),
            'run_number' => 1,
            'status' => GradingStatus::Queued,
            'checks' => null,
            'stdout' => null,
            'stderr' => null,
            'exit_code' => null,
            'error_code' => null,
            'runner_metadata' => null,
            'started_at' => null,
            'finished_at' => null,
        ];
    }
}
