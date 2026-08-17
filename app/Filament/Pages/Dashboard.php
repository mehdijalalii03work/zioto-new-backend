<?php

namespace App\Filament\Pages;

use App\Enums\Permission;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    public static function canAccess(): bool
    {
        return auth()->user()?->hasPermissionTo(Permission::DashboardView->value) ?? false;
    }
}
