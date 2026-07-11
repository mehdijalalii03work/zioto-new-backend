<?php

namespace App\Filament\Resources\Permissions\Pages;

use App\Filament\Resources\Permissions\PermissionResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPermission extends EditRecord
{
    protected static string $resource = PermissionResource::class;

    protected static ?string $title = 'ویرایش دسترسی';

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->label('حذف دسترسی'),
        ];
    }
}
