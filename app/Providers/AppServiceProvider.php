<?php

namespace App\Providers;

use App\Listeners\ConvertMediaToWebp;
use App\Models\User;
use App\Observers\HesabfaObserver;
use App\Observers\ProductImageObserver;
use App\Observers\StockReservationObserver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\ServiceProvider;
use Laravel\Pulse\Facades\Pulse;
use Modules\Order\Models\Order;
use Modules\Product\Models\ProductImage;
use Spatie\MediaLibrary\MediaCollections\Events\MediaAdded;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->configurePulse();

        Order::observe(HesabfaObserver::class);
        Order::observe(StockReservationObserver::class);
        ProductImage::observe(ProductImageObserver::class);

        $this->app['events']->listen(MediaAdded::class, ConvertMediaToWebp::class);

        $interval = config('hesabfa.sync_interval', 60);
        Schedule::command('hesabfa:sync-stock')->cron("*/{$interval} * * * *");

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

    protected function configurePulse(): void
    {
        Gate::define('viewPulse', function (User $user) {
            return $user->hasRole('admin');
        });

        Pulse::user(fn ($user) => [
            'name' => trim(($user->first_name ?? '').' '.($user->last_name ?? '')) ?: $user->name,
            'extra' => $user->email,
            'avatar' => null,
        ]);
    }
}
