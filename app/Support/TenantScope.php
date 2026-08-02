<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Global scope that automatically filters every query on tenant-scoped
 * models to the current platform (from the X-Platform header).
 *
 * Admin contexts (Filament, console commands) must opt out explicitly via
 * `Model::withoutTenantScope()` or `Model::onPlatform(...)`.
 */
class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $platform = Platform::fromRequest();

        // No platform (e.g. console) → keep everything visible.
        if ($platform === null) {
            return;
        }

        $builder->where($model->getTable().'.platform', $platform);
    }
}
