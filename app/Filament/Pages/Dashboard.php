<?php

namespace App\Filament\Pages;

use App\Enums\Permission;
use App\Filament\Pages\Concerns\HasAnalyticsFiltersForm;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    // use HasAnalyticsFiltersForm;

    public static function canAccess(): bool
    {
        return auth()->user()?->hasPermissionTo(Permission::DashboardView->value) ?? false;
    }

    /**
     * @return int|array<string, int>
     */
    public function getColumns(): int|array
    {
        return ['@lg' => 2, '@2xl' => 3];
    }
}
