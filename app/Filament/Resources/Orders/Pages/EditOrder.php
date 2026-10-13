<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Enums\Permission;
use App\Filament\Resources\Orders\OrderResource;
use App\Observers\HesabfaObserver;
use App\Services\Inventory\SerialInventoryService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Order\Models\Order;

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
                ->authorize(Permission::HesabfaSync->value)
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

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return DB::transaction(function () use ($record, $data): Model {
            $current = Order::withoutTenantScope()->lockForUpdate()->findOrFail($record->id);
            if (array_key_exists('items', $data) && $current->payment_status === 'paid') {
                throw ValidationException::withMessages(['data.items' => 'اقلام سفارش پرداخت‌شده قابل تغییر نیست.']);
            }
            if (($data['status'] ?? null) === 'cancelled' && $current->status !== 'cancelled') {
                if (! app(SerialInventoryService::class)->cancelUnpaidOrder($current->id, $current->platform, 'لغو از ویرایش سفارش')) {
                    throw ValidationException::withMessages(['data.status' => 'سفارش پرداخت‌شده یا دارای پرداخت باز قابل لغو نیست.']);
                }
                unset($data['status']);
            }
            $current->update($data);

            return $current;
        }, 3);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (array_key_exists('items', $data)) {
            if ($this->getRecord()->payment_status === 'paid') {
                throw ValidationException::withMessages(['items' => 'اقلام سفارش پرداخت‌شده قابل تغییر نیست.']);
            }
            $data['total_amount'] = collect($data['items'])->sum('subtotal');
        } else {
            unset($data['total_amount']);
        }

        return $data;
    }
}
