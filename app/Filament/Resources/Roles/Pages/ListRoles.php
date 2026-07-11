<?php

namespace App\Filament\Resources\Roles\Pages;

use App\Filament\Resources\Roles\RoleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRoles extends ListRecords
{
    protected static string $resource = RoleResource::class;

    protected static ?string $title = 'لیست نقش‌ها';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('نقش جدید'),
        ];
    }
}
