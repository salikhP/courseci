<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Course\IndexCourseRequest;
use App\Http\Requests\Api\V1\Course\StoreCourseRequest;
use App\Http\Requests\Api\V1\Course\UpdateCourseRequest;
use App\Http\Resources\Api\V1\CourseResource;
use App\Models\Course;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class CourseController extends Controller
{
    public function index(IndexCourseRequest $request): AnonymousResourceCollection
    {
        $user = $request->user();
        $courses = Course::query()->with('teacher');

        if ($user->role === UserRole::Teacher) {
            $courses->whereBelongsTo($user, 'teacher');
        } else {
            $courses->whereHas(
                'enrollments',
                fn (Builder $query): Builder => $query->whereBelongsTo($user, 'student'),
            );
        }

        match ($request->string('status', 'active')->toString()) {
            'active' => $courses->whereNull('archived_at'),
            'archived' => $courses->whereNotNull('archived_at'),
            'all' => $courses,
        };

        return CourseResource::collection(
            $courses->orderByDesc('created_at')->orderByDesc('id')->paginate()->withQueryString(),
        );
    }

    public function store(StoreCourseRequest $request): JsonResponse
    {
        $course = $request->user()->taughtCourses()->create(
            $request->safe()->only(['title', 'description']),
        );

        return (new CourseResource($course->load('teacher')))
            ->response()
            ->setStatusCode(HttpResponse::HTTP_CREATED);
    }

    public function show(Course $course): CourseResource
    {
        Gate::authorize('view', $course);

        return new CourseResource($course->load('teacher'));
    }

    public function update(UpdateCourseRequest $request, Course $course): CourseResource
    {
        $course->update($request->safe()->only(['title', 'description']));

        return new CourseResource($course->load('teacher'));
    }

    public function destroy(Course $course): Response
    {
        Gate::authorize('delete', $course);

        if ($course->archived_at === null) {
            $course->update(['archived_at' => now()]);
        }

        return response()->noContent();
    }
}
