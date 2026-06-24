<?php

use App\Http\Controllers\Api\AddressController;
use App\Http\Controllers\Api\HesabfaWebhookController;
use App\Http\Controllers\Api\LocationController;
use App\Http\Controllers\Api\OrderSubmitController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\PriceBoardController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ShippingController;
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

    Route::middleware('auth', 'throttle:30,1')->prefix('addresses')->group(function () {
        Route::get('/', [AddressController::class, 'index']);
        Route::post('/', [AddressController::class, 'store']);
        Route::get('/{address}', [AddressController::class, 'show']);
        Route::put('/{address}', [AddressController::class, 'update']);
        Route::delete('/{address}', [AddressController::class, 'destroy']);
        Route::put('/{address}/default', [AddressController::class, 'setDefault']);
    });

    Route::prefix('locations')->group(function () {
        Route::get('/provinces', [LocationController::class, 'provinces']);
        Route::get('/provinces/{province}/cities', [LocationController::class, 'cities']);
    });

    Route::prefix('shipping')->group(function () {
        Route::get('/methods', [ShippingController::class, 'methods']);
        Route::get('/methods/{id}', [ShippingController::class, 'show']);
        Route::post('/calculate', [ShippingController::class, 'calculate']);
    });

    Route::get('/orders', [OrderSubmitController::class, 'index'])->middleware('auth');

    Route::post('/orders', [OrderSubmitController::class, 'store']);

    Route::prefix('hesabfa')->group(function () {
        Route::post('/webhook', [HesabfaWebhookController::class, 'handle'])->name('hesabfa.webhook');
        Route::get('/webhook', [HesabfaWebhookController::class, 'test']);
    });

    Route::get('/price-board', [PriceBoardController::class, 'index']);

    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/products/{id}', [ProductController::class, 'show']);

    Route::middleware('auth', 'throttle:60,1')->prefix('cart')->group(function () {
        Route::get('/', [CartController::class, 'index']);
        Route::post('/', [CartController::class, 'store']);
        Route::put('/{productId}', [CartController::class, 'update']);
        Route::delete('/{productId}', [CartController::class, 'destroy']);
        Route::delete('/', [CartController::class, 'clear']);
    });
});
