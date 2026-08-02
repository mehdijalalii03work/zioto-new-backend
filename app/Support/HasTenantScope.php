<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Adds the TenantScope to a model and exposes helpers to opt out
 * (admin panel, console commands) or query a specific platform.
 */
trait HasTenantScope
{
    public static function bootHasTenantScope(): void
    {
        static::addGlobalScope(new TenantScope);
    }

    /**
     * Query without any platform filter (admin/console).
     */
    public static function withoutTenantScope(): Builder
    {
        return static::withoutGlobalScope(TenantScope::class);
    }

    /**
     * Query a specific platform, ignoring the request header.
     */
    public static function onPlatform(string $platform): Builder
    {
        return static::withoutGlobalScope(TenantScope::class)
            ->where((new static)->getTable().'.platform', $platform);
    }

    /**
     * Convenience: query the nopay platform.
     */
    public static function onNopay(): Builder
    {
        return static::onPlatform(Platform::NOPAY);
    }
}
