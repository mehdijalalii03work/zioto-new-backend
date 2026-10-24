<?php

namespace App\Filament\Resources\DiscountCodes\Pages;

use App\Filament\Resources\DiscountCodes\DiscountCodeResource;
use Filament\Resources\Pages\CreateRecord;

class CreateDiscountCode extends CreateRecord
{
    protected static string $resource = DiscountCodeResource::class;

    protected static ?string $title = 'ایجاد کد تخفیف';
}
