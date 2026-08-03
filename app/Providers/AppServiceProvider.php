<?php

namespace App\Providers;

use App\Listeners\ConvertMediaToWebp;
use App\Models\User;
use App\Observers\HesabfaObserver;
use App\Observers\ProductImageObserver;
use App\Observers\StockReservationObserver;
use App\Support\TenantScope;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\ServiceProvider;
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
        // Eloquent builders can call ->withoutTenantScope() directly
        // (e.g. Filament's getEloquentQuery() returns a Builder).
        Builder::macro('withoutTenantScope', function (): Builder {
            /** @var Builder $this */
            return $this->withoutGlobalScope(TenantScope::class);
        });

        Order::observe(HesabfaObserver::class);
        Order::observe(StockReservationObserver::class);
        ProductImage::observe(ProductImageObserver::class);

        $this->app['events']->listen(MediaAdded::class, ConvertMediaToWebp::class);

        $this->app->booted(function () {
            Gate::define('viewPulse', function (User $user) {
                return $user->hasRole(['admin', 'manager', 'operator']);
            });
        });

        $interval = config('hesabfa.sync_interval', 60);
        Schedule::command('hesabfa:sync-stock')->cron("*/{$interval} * * * *")->withoutOverlapping();
        Schedule::command('hesabfa:recalculate-reserved')->dailyAt('03:00')->withoutOverlapping();

        RateLimiter::for('otp', function (Request $request) {
            // In production, key by phone so one user's OTP requests don't
            // exhaust a shared key (all users behind Nginx share one IP).
            $phone = $request->input('phone');

            return Limit::perHour(5)->by($phone ?: $request->ip())->response(function () {
                return response()->json(['message' => 'تعداد درخواست‌ها بیش از حد مجاز است، لطفاً بعداً تلاش کنید'], 429);
            });
        });

        RateLimiter::for('shahkar', function (Request $request) {
            // Key by the national code being verified (sent in the body),
            // falling back to IP. Prevents a shared Nginx IP blocking everyone.
            $nationalCode = $request->input('national_code');

            return Limit::perHour(5)->by($nationalCode ?: $request->ip())->response(function () {
                return response()->json(['message' => 'تعداد درخواست‌ها بیش از حد مجاز است، لطفاً بعداً تلاش کنید'], 429);
            });
        });
    }
}
