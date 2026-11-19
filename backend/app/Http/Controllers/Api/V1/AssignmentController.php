<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Assignment\IndexAssignmentRequest;
use App\Http\Requests\Api\V1\Assignment\StoreAssignmentRequest;
use App\Http\Requests\Api\V1\Assignment\UpdateAssignmentRequest;
use App\Http\Resources\Api\V1\AssignmentResource;
use App\Models\Assignment;
use App\Models\Course;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class AssignmentController extends Controller
{
    public function index(IndexAssignmentRequest $request, Course $course): AnonymousResourceCollection
    {
        $assignments = $course->assignments()->with('gradeItem.course');
        $status = $request->string('status', 'active')->toString();

        $assignments->whereHas('gradeItem', function (Builder $query) use ($status): void {
            match ($status) {
                'active' => $query->whereNull('archived_at'),
                'archived' => $query->whereNotNull('archived_at'),
                'all' => $query,
            };
        });

        return AssignmentResource::collection(
            $assignments->orderByDesc('assignments.created_at')->orderByDesc('assignments.id')
                ->paginate()->withQueryString(),
        );
    }

    public function show(Course $course, Assignment $assignment): AssignmentResource
    {
        Gate::authorize('view', $assignment);

        return new AssignmentResource($assignment->load('gradeItem.course'));
    }

    public function store(StoreAssignmentRequest $request, Course $course): JsonResponse
    {
        $assignment = DB::transaction(function () use ($request, $course): Assignment {
            $course = Course::query()->whereKey($course->id)->lockForUpdate()->firstOrFail();
            Gate::authorize('create', [Assignment::class, $course]);

            $gradeItem = $course->gradeItems()->create(
                $request->safe()->only(['title', 'description', 'max_score']),
            );

            return $gradeItem->assignment()->create($request->scheduleAttributes());
        });

        return (new AssignmentResource($assignment->load('gradeItem.course')))
            ->response()->setStatusCode(HttpResponse::HTTP_CREATED);
    }

    public function update(UpdateAssignmentRequest $request, Course $course, Assignment $assignment): AssignmentResource
    {
        $assignment = DB::transaction(function () use ($request, $course, $assignment): Assignment {
            $course = Course::query()->whereKey($course->id)->lockForUpdate()->firstOrFail();
            $assignment = Assignment::query()->whereKey($assignment->id)->lockForUpdate()->firstOrFail();
            $gradeItem = $assignment->gradeItem()->lockForUpdate()->firstOrFail();
            $gradeItem->setRelation('course', $course);
            $assignment->setRelation('gradeItem', $gradeItem);
            Gate::authorize('update', $assignment);
            $request->validateChanges($assignment);

            $gradeItem->update($request->safe()->only(['title', 'description', 'max_score']));
            $assignment->fill($request->scheduleAttributes())->touch();

            return $assignment;
        });

        return new AssignmentResource($assignment);
    }

    public function destroy(Course $course, Assignment $assignment): Response
    {
        Gate::authorize('delete', $assignment);

        DB::transaction(function () use ($course, $assignment): void {
            $course = Course::query()->whereKey($course->id)->lockForUpdate()->firstOrFail();
            $assignment = Assignment::query()->whereKey($assignment->id)->lockForUpdate()->firstOrFail();
            $gradeItem = $assignment->gradeItem()->lockForUpdate()->firstOrFail();
            $gradeItem->setRelation('course', $course);
            $assignment->setRelation('gradeItem', $gradeItem);
            Gate::authorize('delete', $assignment);

            if ($gradeItem->archived_at === null) {
                $gradeItem->update(['archived_at' => now()]);
                $assignment->touch();
            }
        });

        return response()->noContent();
    }
}
