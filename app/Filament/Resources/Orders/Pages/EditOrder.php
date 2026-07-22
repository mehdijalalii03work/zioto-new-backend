<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use App\Observers\HesabfaObserver;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditOrder extends EditRecord
{
    protected static string $resource = OrderResource::class;

    protected static ?string $title = 'ویرایش سفارش';

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make()
                ->label('مشاهده')
                ->url(fn ($record): string => $this->getResource()::getUrl('view', ['record' => $record])),

            Action::make('syncToHesabfa')
                ->label('ارسال به حسابفا')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('warning')
                ->visible(fn ($record) => ! $record->hesabfa_synced_at)
                ->action(function ($record) {
                    $observer = app(HesabfaObserver::class);
                    $result = $observer->syncOrder($record, force: true);

                    if ($result['success']) {
                        Notification::make()
                            ->title($result['message'])
                            ->success()
                            ->send();
                    } else {
                        Notification::make()
                            ->title($result['message'])
                            ->danger()
                            ->send();
                    }

                    $this->refreshFormData(['hesabfa_contact_code', 'hesabfa_invoice_number', 'hesabfa_synced_at']);
                }),

            DeleteAction::make()
                ->label('حذف سفارش'),

            RestoreAction::make()
                ->label('بازیابی سفارش'),

            ForceDeleteAction::make()
                ->label('حذف دائمی'),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['total_amount'] = collect($data['items'] ?? [])->sum('subtotal') ?? 0;

        return $data;
    }
}
