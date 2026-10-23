<?php

namespace Database\Seeders;

use App\Enums\GradingStatus;
use App\Models\Assignment;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\GradeItem;
use App\Models\GradingRun;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $teacher = User::factory()->teacher()->create([
            'name' => 'Ada Teacher',
            'email' => 'teacher@example.com',
        ]);

        $students = collect([
            ['name' => 'Alex Student', 'email' => 'alex@example.com'],
            ['name' => 'Blair Student', 'email' => 'blair@example.com'],
            ['name' => 'Casey Student', 'email' => 'casey@example.com'],
            ['name' => 'Drew Student', 'email' => 'drew@example.com'],
            ['name' => 'Emery Student', 'email' => 'emery@example.com'],
        ])->map(fn (array $attributes) => User::factory()->student()->create($attributes));

        $course = Course::factory()->for($teacher, 'teacher')->create([
            'title' => 'Go Programming Fundamentals',
            'description' => 'A practical introduction to writing and testing Go programs.',
        ]);

        $students->each(fn (User $student) => Enrollment::factory()
            ->for($course)
            ->for($student, 'student')
            ->create());

        $firstGradeItem = GradeItem::factory()->for($course)->create([
            'title' => 'Command-line Calculator',
            'description' => 'Build a tested calculator command-line application in Go.',
        ]);

        $firstAssignment = Assignment::factory()->for($firstGradeItem)->create([
            'opens_at' => now()->subDay(),
            'due_at' => now()->addWeek(),
        ]);

        $secondGradeItem = GradeItem::factory()->for($course)->create([
            'title' => 'Concurrent URL Checker',
            'description' => 'Build a concurrent URL health checker in Go.',
        ]);

        $secondAssignment = Assignment::factory()->for($secondGradeItem)->create([
            'opens_at' => now()->addWeek(),
            'due_at' => now()->addWeeks(2),
        ]);

        $midterm = GradeItem::factory()->for($course)->create([
            'title' => 'Midterm Exam',
            'description' => 'In-person written midterm examination.',
            'max_score' => '30.00',
        ]);

        $outcomes = [
            GradingStatus::Passed,
            GradingStatus::Failed,
            GradingStatus::TimedOut,
        ];

        $students->take(3)->each(function (User $student, int $index) use ($firstAssignment, $outcomes, $teacher): void {
            $status = $outcomes[$index];
            $submission = Submission::factory()
                ->for($firstAssignment)
                ->for($student, 'student')
                ->create([
                    'commit_sha' => str_repeat((string) ($index + 1), 40),
                    'status' => $status,
                ]);

            GradingRun::factory()->for($submission)->create([
                'status' => $status,
                'checks' => [
                    ['name' => 'gofmt', 'status' => 'passed'],
                    ['name' => 'go vet', 'status' => $status === GradingStatus::Passed ? 'passed' : 'failed'],
                    ['name' => 'go test', 'status' => $status->value],
                ],
                'stdout' => $status === GradingStatus::Passed ? 'All checks passed.' : null,
                'stderr' => $status === GradingStatus::Passed ? null : 'The grading run did not pass.',
                'exit_code' => $status === GradingStatus::Passed ? 0 : 1,
                'runner_metadata' => ['go_version' => '1.25'],
                'started_at' => now()->subMinute(),
                'finished_at' => now(),
            ]);

            if ($index < 2) {
                Grade::factory()
                    ->forSubmission($submission)
                    ->for($teacher, 'grader')
                    ->create(['score' => $index === 0 ? '92.00' : '68.50']);
            }
        });

        Submission::factory()
            ->for($secondAssignment)
            ->for($students->first(), 'student')
            ->create();

        $students->each(function (User $student, int $index) use ($midterm, $teacher): void {
            Grade::factory()
                ->for($midterm)
                ->for($student, 'student')
                ->for($teacher, 'grader')
                ->create([
                    'score' => number_format(22 + $index, 2, '.', ''),
                    'feedback' => 'Entered from the checked paper exam.',
                ]);
        });
    }
}
