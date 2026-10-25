<?php

namespace App\Filament\Resources\DiscountCodes\Pages;

use App\Filament\Resources\DiscountCodes\DiscountCodeResource;
use Filament\Resources\Pages\EditRecord;

class EditDiscountCode extends EditRecord
{
    protected static string $resource = DiscountCodeResource::class;

    protected static ?string $title = 'ویرایش کد تخفیف';
}
