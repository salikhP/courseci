<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\StoreTokenRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class TokenController extends Controller
{
    public function store(StoreTokenRequest $request): JsonResponse
    {
        $user = User::query()->where('email', $request->string('email')->toString())->first();

        if (! $user || ! Hash::check($request->string('password')->toString(), $user->password)) {
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        $plainTextToken = $user
            ->createToken($request->string('device_name')->toString(), ['mobile'])
            ->plainTextToken;

        return response()->json([
            'data' => [
                'token' => $plainTextToken,
                'token_type' => 'Bearer',
                'user' => (new UserResource($user))->resolve($request),
            ],
        ], HttpResponse::HTTP_CREATED);
    }

    public function destroy(Request $request): Response
    {
        $accessToken = $request->user()?->currentAccessToken();

        abort_unless(
            $accessToken instanceof PersonalAccessToken && $accessToken->can('mobile'),
            HttpResponse::HTTP_FORBIDDEN,
        );

        $accessToken->delete();

        return response()->noContent();
    }
}
