<?php

use App\Http\Controllers\LandingController;
use Illuminate\Support\Facades\Route;

Route::get('/', [LandingController::class, 'home'])->name('landing.home');
Route::get('/about', [LandingController::class, 'about'])->name('landing.about');
Route::get('/products/{slug}', [LandingController::class, 'product'])->name('landing.product');
Route::get('/cart', [LandingController::class, 'cart'])->name('landing.cart');
Route::get('/checkout', [LandingController::class, 'checkout'])->name('landing.checkout');
Route::get('/login', [LandingController::class, 'login'])->name('landing.login');
Route::get('/profile', [LandingController::class, 'profile'])->name('landing.profile');
Route::get('/success', [LandingController::class, 'success'])->name('landing.success');
