<?php

namespace Modules\Product\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Modules\Product\Models\ProductImage;
use Modules\Product\Observers\ProductImageObserver;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [];

    protected static $shouldDiscoverEvents = true;

    protected $observers = [
        ProductImage::class => ProductImageObserver::class,
    ];

    protected function configureEmailVerification(): void {}
}
