<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Modules\Order\Models\Order;

class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    protected static ?string $title = 'لیست سفارشات';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('سفارش جدید'),
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
