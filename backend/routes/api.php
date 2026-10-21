<?php

use App\Http\Controllers\Api\V1\CurrentUserController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::get('/user', CurrentUserController::class)
        ->middleware('auth:sanctum')
        ->name('user.show');
});
