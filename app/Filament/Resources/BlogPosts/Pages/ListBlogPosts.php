<?php

namespace App\Filament\Resources\BlogPosts\Pages;

use App\Filament\Resources\BlogPosts\BlogPostResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBlogPosts extends ListRecords
{
    protected static string $resource = BlogPostResource::class;

    protected static ?string $title = 'لیست پست‌های بلاگ';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('پست جدید'),
        ];
    }
}
