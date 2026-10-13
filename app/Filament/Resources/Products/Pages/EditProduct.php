<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use App\Services\Inventory\ProductInventorySettingsService;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    protected static ?string $title = 'ویرایش محصول';

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->label('حذف محصول'),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            return app(ProductInventorySettingsService::class)->update($record, $data, auth()->user(), $data['inventory_reason'] ?? null);
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(collect($exception->errors())->mapWithKeys(fn ($messages, $field): array => ['data.'.($field === 'reason' ? 'inventory_reason' : $field) => $messages])->all());
        }
    }
}
