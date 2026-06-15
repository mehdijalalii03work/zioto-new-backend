<?php

use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Auth\OtpController;
use Illuminate\Support\Facades\Route;

Route::middleware('web')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('/send-otp', [OtpController::class, 'send'])->middleware('throttle:otp');
        Route::post('/verify-otp', [OtpController::class, 'verify']);
        Route::post('/shahkar-verify', [OtpController::class, 'shahkarVerify'])->middleware('throttle:shahkar');
        Route::post('/logout', [OtpController::class, 'logout']);
    });

    Route::get('/profile', [ProfileController::class, 'show'])->middleware('auth');
    Route::put('/profile', [ProfileController::class, 'update'])->middleware('auth');
});
