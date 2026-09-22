<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\Exports\OrderExcelExport;
use App\Filament\Resources\Orders\OrderResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Modules\Order\Models\Order;
use Morilog\Jalali\Jalalian;

class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    protected static ?string $title = 'لیست سفارشات';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('سفارش جدید'),

            Action::make('export_current_month')
                ->label('خروجی اکسل ماه جاری')
                ->icon('heroicon-o-arrow-down-tray')
                ->authorize('order.view')
                ->action(function () {
                    $now = Jalalian::now();
                    $startDate = $now->getFirstDayOfMonth()->toCarbon()->startOfDay();
                    $endDate = $now->getEndDayOfMonth()->toCarbon()->endOfDay();

                    $orders = Order::query()
                        ->whereBetween('created_at', [$startDate, $endDate])
                        ->get();

                    return app(OrderExcelExport::class)->export($orders);
                }),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('سفارشات فعال')
                ->query(fn ($query) => $query->where('status', '!=', 'cancelled'))
                ->badge(fn () => Order::where('status', '!=', 'cancelled')->count()),
            'pending' => Tab::make('در انتظار بررسی')
                ->query(fn ($query) => $query->where('status', 'pending'))
                ->badge(fn () => Order::where('status', 'pending')->count()),
            'confirmed' => Tab::make('تایید شده')
                ->query(fn ($query) => $query->where('status', 'confirmed'))
                ->badge(fn () => Order::where('status', 'confirmed')->count()),
            'completed' => Tab::make('تکمیل شده')
                ->query(fn ($query) => $query->where('status', 'completed'))
                ->badge(fn () => Order::where('status', 'completed')->count()),
            'cancelled' => Tab::make('لغو شده')
                ->query(fn ($query) => $query->where('status', 'cancelled'))
                ->badge(fn () => Order::where('status', 'cancelled')->count()),
        ];
    }
}
