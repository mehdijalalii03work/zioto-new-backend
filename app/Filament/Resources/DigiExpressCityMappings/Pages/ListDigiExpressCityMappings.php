<?php

namespace App\Filament\Resources\DigiExpressCityMappings\Pages;

use App\Enums\Permission;
use App\Filament\Resources\DigiExpressCityMappings\DigiExpressCityMappingResource;
use App\Services\DigiExpressCitySyncService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Throwable;

class ListDigiExpressCityMappings extends ListRecords
{
    protected static string $resource = DigiExpressCityMappingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('syncCities')
                ->label('به‌روزرسانی فهرست شهرهای دیجی‌اکسپرس')
                ->icon('heroicon-o-arrow-path')
                ->authorize(Permission::ShippingEdit->value)
                ->requiresConfirmation()
                ->action(function (DigiExpressCitySyncService $syncService): void {
                    try {
                        $count = $syncService->sync();
                        Notification::make()
                            ->title("{$count} شهر از دیجی‌اکسپرس همگام شد")
                            ->success()
                            ->send();
                    } catch (Throwable $exception) {
                        report($exception);
                        Notification::make()
                            ->title('دریافت فهرست شهرها انجام نشد؛ تنظیمات اتصال را بررسی کنید')
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }
}
