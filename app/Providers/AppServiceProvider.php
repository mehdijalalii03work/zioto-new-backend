<?php

namespace App\Providers;

use App\Listeners\ConvertMediaToWebp;
use App\Listeners\LogRolePermissionChanges;
use App\Models\Brand;
use App\Models\ContactMessage;
use App\Models\DiscountCode;
use App\Models\Setting;
use App\Models\ShippingMethod;
use App\Models\User;
use App\Observers\DiscountCodeUsageObserver;
use App\Observers\HesabfaObserver;
use App\Observers\OrderSmsNotificationObserver;
use App\Observers\PricingSettingAuditObserver;
use App\Observers\ProductImageObserver;
use App\Observers\ProductPricingAuditObserver;
use App\Observers\RolePermissionAuditObserver;
use App\Observers\StockReservationObserver;
use App\Policies\BlogCategoryPolicy;
use App\Policies\BlogPostPolicy;
use App\Policies\BlogTagPolicy;
use App\Policies\BrandPolicy;
use App\Policies\ContactMessagePolicy;
use App\Policies\DiscountCodePolicy;
use App\Policies\OrderPolicy;
use App\Policies\PaymentPolicy;
use App\Policies\PermissionPolicy;
use App\Policies\ProductCategoryPolicy;
use App\Policies\ProductPolicy;
use App\Policies\RolePolicy;
use App\Policies\ShippingMethodPolicy;
use App\Policies\UserPolicy;
use App\Services\WebpConverter;
use App\Support\TenantScope;
use Filament\Forms\Components\BaseFileUpload;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\ServiceProvider;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
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
        Order::observe(DiscountCodeUsageObserver::class);
        Order::observe(StockReservationObserver::class);
        Order::observe(OrderSmsNotificationObserver::class);
        ProductImage::observe(ProductImageObserver::class);
        Product::observe(ProductPricingAuditObserver::class);
        Setting::observe(PricingSettingAuditObserver::class);

        $this->app['events']->listen(MediaAdded::class, ConvertMediaToWebp::class);

        FileUpload::configureUsing(static function (FileUpload $component): void {
            // Spatie's upload component has its own media-library save handler;
            // its MediaAdded listener converts the stored original instead.
            if ($component instanceof SpatieMediaLibraryFileUpload) {
                return;
            }

            $component->saveUploadedFileUsing(static function (BaseFileUpload $component, TemporaryUploadedFile $file): ?string {
                $optimizedFile = WebpConverter::convertUploadedFile($file);

                if ($optimizedFile === $file) {
                    return $component->saveUploadedFile($file);
                }

                try {
                    $storedName = $component->getUploadedFileNameForStorage($file);
                    $webpName = pathinfo($storedName, PATHINFO_FILENAME).'.webp';
                    $path = $component->getDisk()->putFileAs(
                        $component->getDirectory() ?? '',
                        $optimizedFile,
                        $webpName,
                        [
                            'mimetype' => 'image/webp',
                            'visibility' => $component->getVisibility(),
                        ],
                    );

                    return is_string($path) ? $path : $component->saveUploadedFile($file);
                } catch (\Throwable $exception) {
                    report($exception);

                    return $component->saveUploadedFile($file);
                } finally {
                    $temporaryPath = $optimizedFile->getRealPath();

                    if ($temporaryPath && $optimizedFile !== $file && is_file($temporaryPath)) {
                        @unlink($temporaryPath);
                    }
                }
            });
        }, isImportant: true);

        foreach ([RichEditor::class, MarkdownEditor::class] as $editorClass) {
            $editorClass::configureUsing(static function (RichEditor|MarkdownEditor $component): void {
                $component->saveUploadedFileAttachmentUsing(static function (RichEditor|MarkdownEditor $component, TemporaryUploadedFile $file): mixed {
                    return self::storeRichTextAttachment($component, $file);
                });
            }, isImportant: true);
        }

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

    private static function storeRichTextAttachment(RichEditor|MarkdownEditor $component, TemporaryUploadedFile $file): mixed
    {
        $optimizedFile = WebpConverter::convertUploadedFile($file);

        if ($optimizedFile === $file) {
            return self::storeOriginalRichTextAttachment($component, $file);
        }

        try {
            $path = $component->getFileAttachmentsDisk()->putFile(
                $component->getFileAttachmentsDirectory(),
                $optimizedFile,
                [
                    'mimetype' => 'image/webp',
                    'visibility' => $component->getFileAttachmentsVisibility(),
                ],
            );

            return is_string($path) ? $path : self::storeOriginalRichTextAttachment($component, $file);
        } catch (\Throwable $exception) {
            report($exception);

            return self::storeOriginalRichTextAttachment($component, $file);
        } finally {
            $temporaryPath = $optimizedFile->getRealPath();

            if ($temporaryPath && $optimizedFile !== $file && is_file($temporaryPath)) {
                @unlink($temporaryPath);
            }
        }
    }

    private static function storeOriginalRichTextAttachment(RichEditor|MarkdownEditor $component, TemporaryUploadedFile $file): mixed
    {
        if (filled($savedFile = $component->defaultSaveUploadedFileAttachment($file))) {
            return $savedFile;
        }

        $path = $file->store($component->getFileAttachmentsDirectory(), [
            'disk' => $component->getFileAttachmentsDiskName(),
            'mimetype' => $file->getMimeType(),
        ]);

        if ($component->getFileAttachmentsVisibility() === 'public') {
            rescue(fn () => $component->getFileAttachmentsDisk()->setVisibility($path, 'public'), report: false);
        }

        return $path;
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
        Gate::policy(DiscountCode::class, DiscountCodePolicy::class);
        Gate::policy(RoleModel::class, RolePolicy::class);
        Gate::policy(PermissionModel::class, PermissionPolicy::class);
    }
}
