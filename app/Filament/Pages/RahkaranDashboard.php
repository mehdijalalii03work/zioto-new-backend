<?php

namespace App\Filament\Pages;

use App\Enums\Permission;
use App\Filament\Pages\Hesabfa\HesabfaSyncLogs;
use App\Models\HesabfaSyncLog;
use App\Models\RahkaranSerial;
use App\Services\RahkaranInventoryService;
use App\Services\RahkaranService;
use App\Services\RahkaranSettings;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Cache;
use Modules\Order\Models\Order;
use Modules\Product\Models\Product;

class RahkaranDashboard extends Page
{
    protected string $view = 'filament.pages.rahkaran-dashboard';

    protected static ?string $slug = 'rahkaran-dashboard';

    protected static ?string $title = 'داشبورد راهکاران';

    public static function canAccess(): bool
    {
        return auth()->user()?->can(Permission::HesabfaView->value) ?? false;
    }

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-building-library';
    }

    public static function getNavigationLabel(): string
    {
        return 'راهکاران';
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return 'حسابداری';
    }

    public static function getNavigationSort(): ?int
    {
        return 3;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('testConnection')
                ->label('آزمون اتصال')
                ->icon('heroicon-o-signal')
                ->color('gray')
                ->action(fn () => $this->testConnection()),
            Action::make('syncInventory')
                ->label('همگام‌سازی موجودی')
                ->icon('heroicon-o-arrow-path')
                ->color('primary')
                ->authorize(Permission::HesabfaSync->value)
                ->action(fn () => $this->syncInventory()),
            Action::make('settings')
                ->label('تنظیمات راهکاران')
                ->icon('heroicon-o-cog-6-tooth')
                ->color('gray')
                ->visible(fn (): bool => auth()->user()?->can(Permission::SettingView->value) ?? false)
                ->url(ManageSettings::getUrl()),
            Action::make('logs')
                ->label('همهٔ رخدادها')
                ->icon('heroicon-o-document-text')
                ->color('gray')
                ->url(HesabfaSyncLogs::getUrl()),
        ];
    }

    protected function getViewData(): array
    {
        $settings = app(RahkaranSettings::class);
        $productRefs = $settings->get('product_refs');
        $productRefs = is_array($productRefs) ? $productRefs : [];
        $eligibleProducts = Product::query()->publiclyListed()->whereNotNull('sku');
        $totalProductCount = (clone $eligibleProducts)->count();
        $mappedProductCount = $productRefs === []
            ? 0
            : (clone $eligibleProducts)->whereIn('sku', array_keys($productRefs))->count();
        $productsWithoutMapping = $productRefs === []
            ? (clone $eligibleProducts)->orderBy('name')->limit(6)->get(['id', 'name', 'sku'])
            : (clone $eligibleProducts)->whereNotIn('sku', array_keys($productRefs))->orderBy('name')->limit(6)->get(['id', 'name', 'sku']);
        $serialCounts = RahkaranSerial::query()
            ->select('status')
            ->selectRaw('COUNT(*) AS total')
            ->groupBy('status')
            ->pluck('total', 'status');
        $orderCounts = Order::query()
            ->where('payment_status', 'paid')
            ->whereIn('status', ['confirmed', 'completed'])
            ->selectRaw("COALESCE(rahkaran_sync_status, 'pending') AS sync_status, COUNT(*) AS total")
            ->groupBy('rahkaran_sync_status')
            ->pluck('total', 'sync_status');
        $recentLogs = HesabfaSyncLog::query()
            ->with('order:id,order_number')
            ->whereIn('sync_type', ['rahkaran_invoice', 'rahkaran_stock_sync'])
            ->latest()
            ->limit(8)
            ->get();
        $latestStockSync = HesabfaSyncLog::query()
            ->where('sync_type', 'rahkaran_stock_sync')
            ->latest()
            ->first();
        $connectionCheck = Cache::get('rahkaran:last-connection-check');
        $baseUrl = (string) $settings->get('base_url');
        $configured = app(RahkaranService::class)->isConfigured();

        return [
            'inventorySource' => setting('inventory_source', 'hesabfa'),
            'invoiceEnabled' => (bool) setting('send_invoices_to_rahkaran', false),
            'serialTrackingEnabled' => (bool) setting('rahkaran_serial_tracking_enabled', false),
            'baseUrlHost' => parse_url($baseUrl, PHP_URL_HOST) ?: null,
            'configured' => $configured,
            'connectionCheck' => $connectionCheck,
            'productStats' => [
                'total' => $totalProductCount,
                'mapped' => $mappedProductCount,
                'unmapped' => max(0, $totalProductCount - $mappedProductCount),
                'serialTracked' => (clone $eligibleProducts)->where('rahkaran_serial_tracked', true)->count(),
                'withoutSerial' => (clone $eligibleProducts)->where('rahkaran_serial_tracked', false)->count(),
            ],
            'serialStats' => [
                'available' => (int) ($serialCounts['available'] ?? 0),
                'reserved' => (int) ($serialCounts['reserved'] ?? 0),
                'sold' => (int) ($serialCounts['sold'] ?? 0),
            ],
            'orderStats' => [
                'pending' => (int) ($orderCounts['pending'] ?? 0),
                'processing' => (int) ($orderCounts['processing'] ?? 0),
                'success' => (int) ($orderCounts['success'] ?? 0),
                'failed' => (int) ($orderCounts['failed'] ?? 0),
            ],
            'productsWithoutMapping' => $productsWithoutMapping,
            'recentLogs' => $recentLogs,
            'latestStockSync' => $latestStockSync,
        ];
    }

    public function testConnection(): void
    {
        try {
            $result = app(RahkaranService::class)->testConnection();
            $check = [
                'success' => $result['success'],
                'message' => 'اتصال راهکاران برقرار است.',
                'checked_at' => now()->toIso8601String(),
                'server_time' => (string) ($result['server_time'] ?? ''),
            ];

            Cache::put('rahkaran:last-connection-check', $check, now()->addDay());
            Notification::make()->title($check['message'])->success()->send();
        } catch (\Throwable $exception) {
            $check = [
                'success' => false,
                'message' => $exception->getMessage(),
                'checked_at' => now()->toIso8601String(),
            ];

            Cache::put('rahkaran:last-connection-check', $check, now()->addDay());
            Notification::make()->title('آزمون اتصال راهکاران ناموفق بود')->body($check['message'])->danger()->send();
        }
    }

    public function syncInventory(): void
    {
        abort_unless(auth()->user()?->can(Permission::HesabfaSync->value), 403);

        $result = app(RahkaranInventoryService::class)->syncAllSerials();
        $notification = Notification::make()->title($result['message']);

        if ($result['success']) {
            $notification->success();
        } else {
            $notification->danger();
        }

        $notification->send();
    }
}
