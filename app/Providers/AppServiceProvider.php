<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        RateLimiter::for('otp', function (Request $request) {
            if (app()->isLocal()) {
                return Limit::none();
            }

            $phone = $request->input('phone');

            return Limit::perHour(5)->by($phone ?? $request->ip())->response(function () {
                return response()->json(['message' => 'تعداد درخواست‌ها بیش از حد مجاز است، لطفاً بعداً تلاش کنید'], 429);
            });
        });

        RateLimiter::for('shahkar', function (Request $request) {
            if (app()->isLocal()) {
                return Limit::none();
            }

            return Limit::perHour(5)->by($request->ip())->response(function () {
                return response()->json(['message' => 'تعداد درخواست‌ها بیش از حد مجاز است، لطفاً بعداً تلاش کنید'], 429);
            });
        });
    }
}
