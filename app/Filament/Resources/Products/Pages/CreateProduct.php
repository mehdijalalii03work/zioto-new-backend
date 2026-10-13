<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use App\Services\Inventory\ProductInventorySettingsService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Modules\Product\Models\Product;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;

    protected static ?string $title = 'محصول جدید';

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(ProductInventorySettingsService::class)->update(new Product, $data, auth()->user(), $data['inventory_reason'] ?? null);
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(collect($exception->errors())->mapWithKeys(fn ($messages, $field): array => ['data.'.($field === 'reason' ? 'inventory_reason' : $field) => $messages])->all());
        }
    }
}
