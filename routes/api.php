<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);

    // Email verification route (GET for email links)
    Route::get('email/verify/{id}/{hash}', [AuthController::class, 'verifyEmailFromLink'])
        ->middleware(['signed'])
        ->name('verification.verify');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('user', [AuthController::class, 'me']);
        Route::put('user', [AuthController::class, 'updateProfile']);
        Route::post('logout', [AuthController::class, 'logout']);
        Route::post('refresh', [AuthController::class, 'refresh']);
        Route::post('email/verify', [AuthController::class, 'verifyEmail']);
        Route::post('email/verification-notification', [AuthController::class, 'resendVerificationEmail']);
    });
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('users', [UserController::class, 'index'])->middleware('role:admin');
});

