<?php

namespace App\Filament\Resources\BlogCategories\Pages;

use App\Filament\Resources\BlogCategories\BlogCategoryResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditBlogCategory extends EditRecord
{
    protected static string $resource = BlogCategoryResource::class;

    protected static ?string $title = 'ویرایش دسته‌بندی';

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->label('حذف دسته‌بندی'),
        ];
    }
}
