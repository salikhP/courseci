<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\GradeItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GradeItem>
 */
class GradeItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'course_id' => Course::factory(),
            'title' => fake()->sentence(4),
            'description' => fake()->optional()->paragraph(),
            'max_score' => '100.00',
            'archived_at' => null,
        ];
    }
}
