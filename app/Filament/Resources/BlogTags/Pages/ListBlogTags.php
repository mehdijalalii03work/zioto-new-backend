<?php

namespace App\Filament\Resources\BlogTags\Pages;

use App\Filament\Resources\BlogTags\BlogTagResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBlogTags extends ListRecords
{
    protected static string $resource = BlogTagResource::class;

    protected static ?string $title = 'لیست تگ‌های بلاگ';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('تگ جدید'),
        ];
    }
}
