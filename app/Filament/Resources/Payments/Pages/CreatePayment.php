<?php

namespace App\Filament\Resources\Payments\Pages;

use App\Filament\Resources\Payments\PaymentResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePayment extends CreateRecord
{
    protected static string $resource = PaymentResource::class;

    protected static ?string $title = 'تراکنش جدید';

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['transaction_id'] = $data['transaction_id'] ?? 'PAY-'.now()->format('YmdHis');

        return $data;
    }
}
