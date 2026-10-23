<?php

namespace Database\Factories;

use App\Enums\GradingStatus;
use App\Models\Assignment;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Submission>
 */
class SubmissionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'assignment_id' => Assignment::factory(),
            'student_id' => User::factory()->student(),
            'repository_url' => sprintf(
                'https://github.com/%s/%s',
                fake()->userName(),
                fake()->slug(3)
            ),
            'commit_sha' => null,
            'attempt_number' => 1,
            'status' => GradingStatus::Queued,
            'submitted_at' => now(),
        ];
    }
}
