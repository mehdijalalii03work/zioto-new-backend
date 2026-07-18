<?php

use App\Http\Controllers\Api\AddressController;
use App\Http\Controllers\Api\BlogController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\ContactMessageController;
use App\Http\Controllers\Api\HesabfaWebhookController;
use App\Http\Controllers\Api\LocationController;
use App\Http\Controllers\Api\OrderSubmitController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PriceBoardController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ShippingController;
use App\Http\Controllers\Api\WishlistController;
use App\Http\Controllers\Auth\OtpController;
use App\Models\Setting;
use Illuminate\Support\Facades\Route;

Route::middleware('web')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('/send-otp', [OtpController::class, 'send'])->middleware('throttle:otp');
        Route::post('/verify-otp', [OtpController::class, 'verify'])->middleware('throttle:10,1');
        Route::post('/shahkar-verify', [OtpController::class, 'shahkarVerify'])->middleware('throttle:shahkar');
        Route::post('/logout', [OtpController::class, 'logout'])->middleware('auth.token');
    });

    Route::get('/profile', [ProfileController::class, 'show'])->middleware('auth.token');
    Route::put('/profile', [ProfileController::class, 'update'])->middleware('auth.token');
    Route::post('/profile/change-phone/send-otp', [ProfileController::class, 'changePhoneSendOtp'])->middleware('auth.token', 'throttle:otp');
    Route::post('/profile/change-phone/verify', [ProfileController::class, 'changePhoneVerify'])->middleware('auth.token', 'throttle:shahkar');

    Route::middleware('auth.token', 'throttle:30,1')->prefix('addresses')->group(function () {
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
        Route::get('/methods', [ShippingController::class, 'methods'])->middleware('auth.token');
        Route::get('/methods/{id}', [ShippingController::class, 'show']);
        Route::post('/calculate', [ShippingController::class, 'calculate']);
    });

    Route::get('/orders', [OrderSubmitController::class, 'index'])->middleware('auth.token');

    Route::get('/orders/{orderId}', [OrderSubmitController::class, 'show'])->middleware('auth.token');

    Route::get('/orders/{orderId}/notes', [OrderSubmitController::class, 'notes'])->middleware('auth.token');

    Route::middleware('auth.token', 'throttle:10,1')->post('/orders', [OrderSubmitController::class, 'store']);

    Route::middleware('auth.token')->prefix('wishlist')->group(function () {
        Route::get('/', [WishlistController::class, 'index']);
        Route::post('/', [WishlistController::class, 'store']);
        Route::delete('/{productId}', [WishlistController::class, 'destroy']);
        Route::post('/toggle', [WishlistController::class, 'toggle']);
    });

    Route::prefix('hesabfa')->group(function () {
        Route::post('/webhook', [HesabfaWebhookController::class, 'handle'])->name('hesabfa.webhook');
        Route::get('/webhook', [HesabfaWebhookController::class, 'test']);
    });

    Route::get('/price-board', [PriceBoardController::class, 'index']);

    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/products/{id}', [ProductController::class, 'show']);

    Route::get('/settings/tax', function () {
        return response()->json([
            'tax_rate' => Setting::getValue('tax_rate', 9),
        ]);
    });

    Route::prefix('blog')->group(function () {
        Route::get('/posts', [BlogController::class, 'posts']);
        Route::get('/posts/{slugOrId}', [BlogController::class, 'post']);
        Route::get('/categories', [BlogController::class, 'categories']);
    });

    Route::middleware('throttle:5,1')->post('/contact', [ContactMessageController::class, 'store']);

    Route::middleware('auth.token', 'throttle:60,1')->prefix('cart')->group(function () {
        Route::get('/', [CartController::class, 'index']);
        Route::post('/', [CartController::class, 'store']);
        Route::put('/{productId}', [CartController::class, 'update']);
        Route::delete('/{productId}', [CartController::class, 'destroy']);
        Route::delete('/', [CartController::class, 'clear']);
    });

    Route::post('/payment/calculate-fee', [PaymentController::class, 'calculateFee']);

    Route::middleware('throttle:10,1')->prefix('payment')->group(function () {
        Route::post('/init', [PaymentController::class, 'init']);
        Route::match(['get', 'post'], '/callback/{orderId}/{gateway}', [PaymentController::class, 'callback'])->name('payment.callback');
        Route::get('/status/{orderId}', [PaymentController::class, 'status'])->middleware('auth.token');
    });
});
