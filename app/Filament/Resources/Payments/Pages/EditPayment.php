<?php

namespace App\Filament\Resources\Payments\Pages;

use App\Filament\Resources\Payments\PaymentResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditPayment extends EditRecord
{
    protected static string $resource = PaymentResource::class;

    protected static ?string $title = 'ویرایش تراکنش';

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->label('حذف تراکنش'),

            RestoreAction::make()
                ->label('بازیابی تراکنش'),

            ForceDeleteAction::make()
                ->label('حذف دائمی'),
        ];
    }
}
