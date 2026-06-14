<?php

namespace App\Providers\Filament;

use App\Filament\Auth\Pages\Login as CustomLogin;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Facades\FilamentIcon;
use Filament\View\PanelsIconAlias;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        FilamentIcon::register([
            PanelsIconAlias::SIDEBAR_COLLAPSE_BUTTON => 'heroicon-o-bars-3',
            PanelsIconAlias::SIDEBAR_COLLAPSE_BUTTON_RTL => 'heroicon-o-bars-3',
        ]);

        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login(CustomLogin::class)
            ->darkMode(false)
            ->sidebarCollapsibleOnDesktop()
            ->sidebarWidth('19rem')
            ->favicon(asset('favicon.ico'))
            ->globalSearch(false)
            ->colors([
                'primary' => [
                    50 => '#FBF7EE',
                    100 => '#F5EDD6',
                    200 => '#EDDFB7',
                    300 => '#E0CC91',
                    400 => '#D0B873',
                    500 => '#BFA772',
                    600 => '#A88E5D',
                    700 => '#8C754A',
                    800 => '#705E3C',
                    900 => '#5A4C30',
                    950 => '#332B1A',
                ],
            ])
            ->brandLogo(fn () => view('filament.admin.logo'))
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
