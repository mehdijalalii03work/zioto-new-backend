<?php

namespace App\Filament\Pages\Hesabfa;

use App\Models\HesabfaSyncLog;
use App\Services\HesabfaService;
use App\Services\StockSyncService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Modules\Order\Models\Order;

class HesabfaDashboard extends Page
{
    protected string $view = 'filament.pages.hesabfa-dashboard';

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-home';
    }

    public static function getNavigationLabel(): string
    {
        return 'داشبورد';
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return 'حسابفا';
    }

    public static function getNavigationSort(): ?int
    {
        return 0;
    }

    public function getStats(): array
    {
        $totalOrders = Order::count();
        $syncedOrders = Order::whereNotNull('hesabfa_synced_at')->count();
        $unsyncedOrders = $totalOrders - $syncedOrders;

        return [
            'total_orders' => $totalOrders,
            'synced_orders' => $syncedOrders,
            'unsynced_orders' => $unsyncedOrders,
        ];
    }

    public function getConnectionStatus(): array
    {
        $hesabfa = app(HesabfaService::class);

        if (! $hesabfa->isConfigured()) {
            return ['status' => 'error', 'message' => 'تنظیمات حسابفا یافت نشد'];
        }

        $result = $hesabfa->testConnection();

        return [
            'status' => $result['success'] ? 'success' : 'error',
            'message' => $result['message'],
        ];
    }

    public function getRecentErrors(): array
    {
        return HesabfaSyncLog::with('order')
            ->where('status', 'failed')
            ->latest()
            ->limit(5)
            ->get()
            ->toArray();
    }

    public function getRecentActivity(): array
    {
        return HesabfaSyncLog::with('order')
            ->latest()
            ->limit(10)
            ->get()
            ->toArray();
    }

    public function syncStock(): void
    {
        $stockSync = app(StockSyncService::class);
        $result = $stockSync->syncAllStock();

        if ($result['success']) {
            Notification::make()
                ->title($result['message'])
                ->success()
                ->send();
        } else {
            Notification::make()
                ->title($result['message'])
                ->danger()
                ->send();
        }
    }
}
