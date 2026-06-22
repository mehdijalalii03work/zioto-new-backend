<?php

namespace App\Filament\Resources\ShippingMethods\Pages;

use App\Filament\Resources\ShippingMethods\ShippingMethodResource;
use App\Models\ShippingMethod;
use Filament\Resources\Pages\CreateRecord;

class CreateShippingMethod extends CreateRecord
{
    protected static string $resource = ShippingMethodResource::class;

    protected static ?string $title = 'روش ارسال جدید';

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['id'] = ShippingMethod::max('id') + 1;

        return $data;
    }
}
