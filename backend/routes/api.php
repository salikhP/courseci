<?php

use App\Http\Controllers\Api\V1\Auth\RegisterController;
use App\Http\Controllers\Api\V1\Auth\SessionController;
use App\Http\Controllers\Api\V1\Auth\TokenController;
use App\Http\Controllers\Api\V1\CurrentUserController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::prefix('auth')->name('auth.')->group(function (): void {
        Route::post('/register', RegisterController::class)
            ->middleware('throttle:registration')
            ->name('register');

        Route::post('/session', [SessionController::class, 'store'])
            ->middleware('throttle:authentication')
            ->name('session.store');

        Route::delete('/session', [SessionController::class, 'destroy'])
            ->middleware('auth:web')
            ->name('session.destroy');

        Route::post('/tokens', [TokenController::class, 'store'])
            ->middleware('throttle:authentication')
            ->name('tokens.store');

        Route::delete('/tokens/current', [TokenController::class, 'destroy'])
            ->middleware('auth:sanctum')
            ->name('tokens.destroy');
    });

    Route::get('/user', CurrentUserController::class)
        ->middleware('auth:sanctum')
        ->name('user.show');
});
