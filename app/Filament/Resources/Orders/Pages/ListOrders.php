<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

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
}
