<?php

namespace App\Providers;

use App\Listeners\ConvertMediaToWebp;
use App\Listeners\LogRolePermissionChanges;
use App\Models\Brand;
use App\Models\ContactMessage;
use App\Models\ShippingMethod;
use App\Models\User;
use App\Observers\HesabfaObserver;
use App\Observers\ProductImageObserver;
use App\Observers\RolePermissionAuditObserver;
use App\Observers\StockReservationObserver;
use App\Policies\BlogCategoryPolicy;
use App\Policies\BlogPostPolicy;
use App\Policies\BlogTagPolicy;
use App\Policies\BrandPolicy;
use App\Policies\ContactMessagePolicy;
use App\Policies\OrderPolicy;
use App\Policies\PaymentPolicy;
use App\Policies\PermissionPolicy;
use App\Policies\ProductCategoryPolicy;
use App\Policies\ProductPolicy;
use App\Policies\RolePolicy;
use App\Policies\ShippingMethodPolicy;
use App\Policies\UserPolicy;
use App\Support\TenantScope;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\ServiceProvider;
use Modules\Blog\Models\BlogCategory;
use Modules\Blog\Models\BlogPost;
use Modules\Blog\Models\BlogTag;
use Modules\Order\Models\Order;
use Modules\Payment\Models\Payment;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductCategory;
use Modules\Product\Models\ProductImage;
use Spatie\MediaLibrary\MediaCollections\Events\MediaAdded;
use Spatie\Permission\Events\PermissionAttachedEvent;
use Spatie\Permission\Events\PermissionDetachedEvent;
use Spatie\Permission\Events\RoleAttachedEvent;
use Spatie\Permission\Events\RoleDetachedEvent;
use Spatie\Permission\Models\Permission as PermissionModel;
use Spatie\Permission\Models\Role as RoleModel;

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

        $this->registerPolicies();

        Order::observe(HesabfaObserver::class);
        Order::observe(StockReservationObserver::class);
        ProductImage::observe(ProductImageObserver::class);

        $this->app['events']->listen(MediaAdded::class, ConvertMediaToWebp::class);

        $this->app['events']->listen(
            RoleAttachedEvent::class,
            [LogRolePermissionChanges::class, 'handleRoleAttached'],
        );
        $this->app['events']->listen(
            RoleDetachedEvent::class,
            [LogRolePermissionChanges::class, 'handleRoleDetached'],
        );
        $this->app['events']->listen(
            PermissionAttachedEvent::class,
            [LogRolePermissionChanges::class, 'handlePermissionAttached'],
        );
        $this->app['events']->listen(
            PermissionDetachedEvent::class,
            [LogRolePermissionChanges::class, 'handlePermissionDetached'],
        );

        RoleModel::observe(RolePermissionAuditObserver::class);
        PermissionModel::observe(RolePermissionAuditObserver::class);

        $this->app->booted(function () {
            Gate::define('viewPulse', fn (User $user): bool => $user->isStaff());
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

    private function registerPolicies(): void
    {
        Gate::policy(Product::class, ProductPolicy::class);
        Gate::policy(ProductCategory::class, ProductCategoryPolicy::class);
        Gate::policy(Brand::class, BrandPolicy::class);
        Gate::policy(Order::class, OrderPolicy::class);
        Gate::policy(Payment::class, PaymentPolicy::class);
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(ShippingMethod::class, ShippingMethodPolicy::class);
        Gate::policy(BlogPost::class, BlogPostPolicy::class);
        Gate::policy(BlogCategory::class, BlogCategoryPolicy::class);
        Gate::policy(BlogTag::class, BlogTagPolicy::class);
        Gate::policy(ContactMessage::class, ContactMessagePolicy::class);
        Gate::policy(RoleModel::class, RolePolicy::class);
        Gate::policy(PermissionModel::class, PermissionPolicy::class);
    }
}
