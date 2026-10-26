<?php

namespace Tests\Feature\Http\Middleware;

use App\Enums\UserRole;
use App\Http\Middleware\EnsureUserHasRole;
use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class EnsureUserHasRoleTest extends TestCase
{
    public function test_guest_is_rejected(): void
    {
        $this->expectException(AuthenticationException::class);

        $this->middleware()->handle(Request::create('/'), fn (): Response => response(), 'teacher');
    }

    public function test_user_with_an_allowed_role_passes(): void
    {
        $request = Request::create('/');
        $request->setUserResolver(fn (): User => User::factory()->make([
            'role' => UserRole::Teacher,
        ]));

        $response = $this->middleware()->handle(
            $request,
            fn (): Response => response('allowed'),
            'teacher',
        );

        $this->assertSame('allowed', $response->getContent());
    }

    public function test_user_without_an_allowed_role_is_forbidden(): void
    {
        $request = Request::create('/');
        $request->setUserResolver(fn (): User => User::factory()->make([
            'role' => UserRole::Student,
        ]));

        try {
            $this->middleware()->handle($request, fn (): Response => response(), 'teacher');
            $this->fail('Expected a forbidden response.');
        } catch (HttpException $exception) {
            $this->assertSame(Response::HTTP_FORBIDDEN, $exception->getStatusCode());
        }
    }

    private function middleware(): EnsureUserHasRole
    {
        return app(EnsureUserHasRole::class);
    }
}
