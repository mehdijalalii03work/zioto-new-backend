<?php

namespace App\Filament\Resources\DigiExpressCityMappings\Pages;

use App\Filament\Resources\DigiExpressCityMappings\DigiExpressCityMappingResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

class EditDigiExpressCityMapping extends EditRecord
{
    protected static string $resource = DigiExpressCityMappingResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['is_destination_enabled'] = (bool) ($data['is_verified'] ?? false)
            && (bool) ($data['is_destination_enabled'] ?? false);
        $data['verified_by'] = ($data['is_verified'] ?? false) ? Auth::id() : null;
        $data['verified_at'] = ($data['is_verified'] ?? false) ? now() : null;

        return $data;
    }
}
