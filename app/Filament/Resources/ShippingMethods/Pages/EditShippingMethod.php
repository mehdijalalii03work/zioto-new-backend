<?php

namespace App\Filament\Resources\ShippingMethods\Pages;

use App\Filament\Resources\ShippingMethods\ShippingMethodResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditShippingMethod extends EditRecord
{
    protected static string $resource = ShippingMethodResource::class;

    protected static ?string $title = 'ویرایش روش ارسال';

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->label('حذف روش ارسال'),
        ];
    }
}
