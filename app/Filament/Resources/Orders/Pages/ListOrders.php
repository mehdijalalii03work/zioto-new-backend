<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;

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
            'all' => Tab::make('همه سفارشات'),
            'pending' => Tab::make('در انتظار بررسی')
                ->query(fn ($query) => $query->where('status', 'pending')),
            'confirmed' => Tab::make('تایید شده')
                ->query(fn ($query) => $query->where('status', 'confirmed')),
            'processing' => Tab::make('در حال پردازش')
                ->query(fn ($query) => $query->where('status', 'processing')),
            'shipped' => Tab::make('ارسال شده')
                ->query(fn ($query) => $query->where('status', 'shipped')),
            'delivered' => Tab::make('تحویل شده')
                ->query(fn ($query) => $query->where('status', 'delivered')),
            'cancelled' => Tab::make('لغو شده')
                ->query(fn ($query) => $query->where('status', 'cancelled')),
        ];
    }
}
