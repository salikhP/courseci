<?php

namespace App\Providers;

use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\Parameter;
use Dedoc\Scramble\Support\Generator\Response as OpenApiResponse;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\SecurityRequirement;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('registration', fn (Request $request) => Limit::perMinute(5)
            ->by($request->ip()));

        RateLimiter::for('authentication', function (Request $request): Limit {
            $email = Str::lower($request->string('email')->trim()->toString());

            return Limit::perMinute(5)->by(Str::transliterate($email.'|'.$request->ip()));
        });

        $this->configureOpenApi();
    }

    private function configureOpenApi(): void
    {
        if (! class_exists(Scramble::class)) {
            return;
        }

        Scramble::configure()->withDocumentTransformers(function (OpenApi $openApi): void {
            $openApi->security = [];
            $openApi->components
                ->addSecurityScheme(
                    'bearerToken',
                    SecurityScheme::http('bearer')
                        ->as('bearerToken')
                        ->setDescription('Sanctum mobile access token.'),
                )
                ->addSecurityScheme(
                    'sessionCookie',
                    SecurityScheme::apiKey('cookie', (string) config('session.cookie'))
                        ->as('sessionCookie')
                        ->setDescription('Sanctum SPA session cookie.'),
                );

            $securityByOperation = [
                'v1.user.show' => ['bearerToken', 'sessionCookie'],
                'v1.auth.session.destroy' => ['sessionCookie'],
                'v1.auth.tokens.destroy' => ['bearerToken'],
            ];

            $metadataByOperation = [
                'sanctum.csrf-cookie' => ['Initialize SPA CSRF protection', 'Sets the XSRF-TOKEN cookie required by stateful SPA requests.'],
                'v1.auth.register' => ['Register a student', 'Creates a student account without starting a session.'],
                'v1.auth.session.store' => ['Start an SPA session', 'Requires a Sanctum CSRF cookie and matching X-XSRF-TOKEN header.'],
                'v1.auth.session.destroy' => ['End the SPA session', 'Requires the current SPA session and X-XSRF-TOKEN header.'],
                'v1.auth.tokens.store' => ['Create a mobile token', 'Creates a Sanctum bearer token for the named mobile device.'],
                'v1.auth.tokens.destroy' => ['Revoke the current mobile token', 'Revokes the bearer token used for this request.'],
                'v1.user.show' => ['Get the current user', 'Accepts either an SPA session or a mobile bearer token.'],
                'v1.courses.index' => ['List accessible courses', 'Teachers receive owned courses; students receive courses in which they are enrolled. Active courses are returned by default.'],
                'v1.courses.store' => ['Create a course', 'Creates a new course owned by the authenticated teacher.'],
                'v1.courses.show' => ['Get a course', 'Available to the owning teacher and enrolled students, including for archived courses.'],
                'v1.courses.update' => ['Update a course', 'Updates an active course owned by the authenticated teacher.'],
                'v1.courses.destroy' => ['Archive a course', 'Archives an owned course without deleting its history.'],
                'v1.courses.enrollments.index' => ['List course enrollments', 'Lists students enrolled in a course owned by the authenticated teacher.'],
                'v1.courses.enrollments.store' => ['Enroll a student', 'Enrolls an existing student account by email in an active owned course.'],
                'v1.courses.enrollments.destroy' => ['Remove an enrollment', 'Removes access to an active course while preserving submissions and grades.'],
                'v1.courses.assignments.index' => ['List course assignments', 'Available to the owning teacher and enrolled students. Returns a paginated collection; status accepts active (default), archived, or all. Manual grade items are excluded.'],
                'v1.courses.assignments.show' => ['Get an assignment', 'Available to the owning teacher and enrolled students. The assignment must belong to the course in the URL. Includes archived assignments for historical access.'],
                'v1.courses.assignments.store' => ['Create an assignment', 'Creates an assignment and journal grade item in an active owned course. Dates are nullable and normalized to UTC; a deadline must follow the opening date. Maximum score is positive with at most two decimal places. Go grading checks are fixed and cannot be configured.'],
                'v1.courses.assignments.update' => ['Update an assignment', 'Partially updates an active assignment in an active owned course. Omitted fields are preserved; optional fields may be cleared. After any submission exists, maximum score and opening date cannot change (422). Title, description, and deadline remain editable.'],
                'v1.courses.assignments.destroy' => ['Archive an assignment', 'Archives an assignment in an active owned course without deleting submissions, grades, or journal history. Repeated requests preserve the original archive timestamp.'],
                'v1.courses.assignments.submissions.index' => ['List assignment submissions', 'The course owner sees every student attempt; students see only their own. Students retain access to their own history after enrollment removal. Optional status filter accepts queued, running, passed, failed, timed_out, or system_error.'],
                'v1.courses.assignments.submissions.show' => ['Get submission and grading feedback', 'Available only to the submitting student and the course owner. Includes the latest grading result, grading-run history, and a grade when one references this attempt. Internal runner metadata is omitted.'],
                'v1.courses.assignments.submissions.store' => ['Submit a repository', 'An enrolled student submits an HTTPS GitHub, GitLab, or Bitbucket owner/repository URL during the assignment window. Requires an Idempotency-Key UUID header: a new key creates an attempt (201), a retry with the same key and canonical URL returns the original (200), and a conflicting URL returns 409. Saves the attempt, initial grading run, and database job atomically. Repository visibility and existence are verified by the Phase 8 runner, not this endpoint.'],
            ];

            foreach ($openApi->paths as $path) {
                foreach ($path->operations as $operation) {
                    if (str_starts_with($operation->operationId, 'v1.courses.')) {
                        $securityByOperation[$operation->operationId] = ['bearerToken', 'sessionCookie'];
                    }

                    $operation->security = array_map(
                        fn (string $scheme): SecurityRequirement => new SecurityRequirement([$scheme => []]),
                        $securityByOperation[$operation->operationId] ?? [],
                    );

                    if (isset($metadataByOperation[$operation->operationId])) {
                        [$summary, $description] = $metadataByOperation[$operation->operationId];
                        $operation->summary($summary)->description($description);
                    }

                    if (in_array($operation->operationId, [
                        'v1.auth.session.store',
                        'v1.auth.session.destroy',
                    ], true)) {
                        $operation->addParameters([
                            Parameter::make('X-XSRF-TOKEN', 'header')
                                ->required(true)
                                ->description('Decoded value of the XSRF-TOKEN cookie.')
                                ->setSchema(Schema::fromType(new StringType)),
                        ]);
                    }

                    if ($operation->operationId === 'v1.courses.assignments.submissions.store') {
                        $operation->addParameters([
                            Parameter::make('Idempotency-Key', 'header')->required(true)
                                ->description('A new UUID per intended attempt. Reuse it for retries of that attempt.')
                                ->setSchema(Schema::fromType((new StringType)->format('uuid'))),
                        ]);

                        foreach ($operation->responses as $response) {
                            if ($response instanceof OpenApiResponse && (int) $response->code === 200) {
                                $response->setDescription('Previously accepted submission returned for an idempotent retry.');
                                $operation->addResponse((clone $response)->setCode(201)
                                    ->setDescription('Submission, initial grading run, and grading job created.'));
                                break;
                            }
                        }

                        $operation->addResponse(OpenApiResponse::make(409)
                            ->setDescription('Idempotency key already used for a different repository URL.')
                            ->setContent('application/json', Schema::fromType((new ObjectType)
                                ->addProperty('message', new StringType)->setRequired(['message']))));
                    }

                    foreach ($operation->responses ?? [] as $response) {
                        if ($response instanceof OpenApiResponse && (int) $response->code === 204) {
                            $response->content = [];
                            $response->setDescription('No content');
                        }
                    }
                }
            }
        });
    }
}
