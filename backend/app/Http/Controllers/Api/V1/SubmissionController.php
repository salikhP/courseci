<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\GradingStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Submission\IndexSubmissionRequest;
use App\Http\Requests\Api\V1\Submission\StoreSubmissionRequest;
use App\Http\Resources\Api\V1\SubmissionResource;
use App\Jobs\GradeSubmission;
use App\Models\Assignment;
use App\Models\Course;
use App\Models\Submission;
use App\Rules\PublicRepositoryUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class SubmissionController extends Controller
{
    public function index(IndexSubmissionRequest $request, Course $course, Assignment $assignment): AnonymousResourceCollection
    {
        $submissions = $assignment->submissions()->with(['student', 'grade', 'latestGradingRun']);

        if ($request->user()->role === UserRole::Student) {
            $submissions->whereBelongsTo($request->user(), 'student');
        }

        if ($request->has('status')) {
            $submissions->where('status', $request->enum('status', GradingStatus::class));
        }

        return SubmissionResource::collection(
            $submissions->orderByDesc('submitted_at')->orderByDesc('id')->paginate()->withQueryString(),
        );
    }

    public function show(Course $course, Assignment $assignment, Submission $submission): SubmissionResource
    {
        Gate::authorize('view', $submission);

        return new SubmissionResource($submission->load(['student', 'grade', 'latestGradingRun', 'gradingRuns']));
    }

    public function store(StoreSubmissionRequest $request, Course $course, Assignment $assignment): JsonResponse
    {
        $result = DB::transaction(function () use ($request, $course, $assignment): array {
            $course = Course::query()->whereKey($course->id)->lockForUpdate()->firstOrFail();
            $assignment = Assignment::query()->whereKey($assignment->id)->lockForUpdate()->firstOrFail();
            $assignment->load('gradeItem');
            $assignment->gradeItem->setRelation('course', $course);
            $course->enrollments()->where('student_id', $request->user()->id)->lockForUpdate()->first();
            Gate::authorize('create', [Submission::class, $assignment]);

            $repositoryUrl = PublicRepositoryUrl::normalize($request->validated('repository_url'));
            $submissions = $assignment->submissions()->whereBelongsTo($request->user(), 'student');
            $existing = (clone $submissions)->where('idempotency_key', $request->validated('idempotency_key'))->first();

            if ($existing !== null) {
                abort_if($existing->repository_url !== $repositoryUrl, HttpResponse::HTTP_CONFLICT,
                    'This idempotency key was already used for a different repository URL.');

                return ['submission' => $existing, 'created' => false];
            }

            if (! $assignment->isOpenForSubmissions()) {
                throw ValidationException::withMessages(['assignment' => 'This assignment is not currently open for submissions.']);
            }

            $submission = $assignment->submissions()->create([
                'student_id' => $request->user()->id,
                'repository_url' => $repositoryUrl,
                'idempotency_key' => $request->validated('idempotency_key'),
                'attempt_number' => ((int) $submissions->max('attempt_number')) + 1,
                'status' => GradingStatus::Queued,
                'submitted_at' => now(),
            ]);
            $submission->gradingRuns()->create(['run_number' => 1, 'status' => GradingStatus::Queued]);
            Bus::dispatch(new GradeSubmission($submission->id));

            return ['submission' => $submission, 'created' => true];
        });

        return (new SubmissionResource($result['submission']->load(['student', 'grade', 'latestGradingRun', 'gradingRuns'])))
            ->response()->setStatusCode($result['created'] ? HttpResponse::HTTP_CREATED : HttpResponse::HTTP_OK);
    }
}
