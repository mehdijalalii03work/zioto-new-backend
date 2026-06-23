<?php

namespace App\Filament\Resources\ProductCategories\Pages;

use App\Filament\Resources\ProductCategories\ProductCategoryResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditProductCategory extends EditRecord
{
    protected static string $resource = ProductCategoryResource::class;

    protected static ?string $title = 'ویرایش دسته‌بندی';

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->label('حذف دسته‌بندی'),
        ];
    }
}
