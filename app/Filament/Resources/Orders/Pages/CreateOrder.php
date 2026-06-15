<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use Filament\Resources\Pages\CreateRecord;

class CreateOrder extends CreateRecord
{
    protected static string $resource = OrderResource::class;

    protected static ?string $title = 'سفارش جدید';

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['order_number'] = 'ZT-'.now()->format('YmdHis');

        $data['total_amount'] = collect($data['items'] ?? [])->sum('subtotal') ?? 0;

        return $data;
    }
}
