<?php

namespace App\Filament\Resources\BlogTags\Pages;

use App\Filament\Resources\BlogTags\BlogTagResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditBlogTag extends EditRecord
{
    protected static string $resource = BlogTagResource::class;

    protected static ?string $title = 'ویرایش تگ';

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->label('حذف تگ'),
        ];
    }
}
