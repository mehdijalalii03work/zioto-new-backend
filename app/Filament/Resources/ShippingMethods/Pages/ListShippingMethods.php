<?php

namespace App\Filament\Resources\ShippingMethods\Pages;

use App\Filament\Resources\ShippingMethods\ShippingMethodResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListShippingMethods extends ListRecords
{
    protected static string $resource = ShippingMethodResource::class;

    protected static ?string $title = 'روش‌های ارسال';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('روش ارسال جدید'),
        ];
    }
}
