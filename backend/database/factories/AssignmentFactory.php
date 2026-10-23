<?php

namespace Database\Factories;

use App\Models\Assignment;
use App\Models\GradeItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Assignment>
 */
class AssignmentFactory extends Factory
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
            'opens_at' => now(),
            'due_at' => now()->addWeek(),
        ];
    }
}
