<?php

namespace App\Filament\Resources\DiscountCodes\Pages;

use App\Filament\Resources\DiscountCodes\DiscountCodeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDiscountCodes extends ListRecords
{
    protected static string $resource = DiscountCodeResource::class;

    protected static ?string $title = 'لیست کدهای تخفیف';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('کد جدید'),
        ];
    }
}
