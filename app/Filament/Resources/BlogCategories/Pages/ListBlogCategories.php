<?php

namespace App\Filament\Resources\BlogCategories\Pages;

use App\Filament\Resources\BlogCategories\BlogCategoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBlogCategories extends ListRecords
{
    protected static string $resource = BlogCategoryResource::class;

    protected static ?string $title = 'لیست دسته‌بندی‌های بلاگ';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('دسته‌بندی جدید'),
        ];
    }
}
