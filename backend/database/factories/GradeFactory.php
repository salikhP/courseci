<?php

namespace Database\Factories;

use App\Models\Grade;
use App\Models\GradeItem;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Grade>
 */
class GradeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'grade_item_id' => GradeItem::factory(),
            'student_id' => User::factory()->student(),
            'submission_id' => null,
            'grader_id' => User::factory()->teacher(),
            'score' => fn (array $attributes) => fake()->randomFloat(
                2,
                0,
                (float) GradeItem::query()->findOrFail($attributes['grade_item_id'])->max_score
            ),
            'feedback' => fake()->optional()->paragraph(),
            'graded_at' => now(),
        ];
    }

    public function forSubmission(Submission $submission): static
    {
        return $this->state(fn (array $attributes) => [
            'submission_id' => $submission->id,
            'grade_item_id' => $submission->assignment->grade_item_id,
            'student_id' => $submission->student_id,
        ]);
    }
}
