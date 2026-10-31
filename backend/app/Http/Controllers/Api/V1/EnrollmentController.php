<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Enrollment\StoreEnrollmentRequest;
use App\Http\Resources\Api\V1\EnrollmentResource;
use App\Models\Course;
use App\Models\Enrollment;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class EnrollmentController extends Controller
{
    public function index(Course $course): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', [Enrollment::class, $course]);

        return EnrollmentResource::collection(
            $course->enrollments()
                ->with('student')
                ->orderByDesc('enrolled_at')
                ->orderByDesc('id')
                ->paginate(),
        );
    }

    public function store(StoreEnrollmentRequest $request, Course $course): JsonResponse
    {
        try {
            $enrollment = $course->enrollments()->create([
                'student_id' => $request->student()->id,
                'enrolled_at' => now(),
            ]);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'email' => 'This student is already enrolled in the course.',
            ]);
        }

        return (new EnrollmentResource($enrollment->load('student')))
            ->response()
            ->setStatusCode(HttpResponse::HTTP_CREATED);
    }

    public function destroy(Course $course, Enrollment $enrollment): Response
    {
        Gate::authorize('delete', $enrollment);

        $enrollment->delete();

        return response()->noContent();
    }
}
