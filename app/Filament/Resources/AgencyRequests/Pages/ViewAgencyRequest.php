<?php

namespace App\Filament\Resources\AgencyRequests\Pages;

use App\Filament\Resources\AgencyRequests\AgencyRequestResource;
use Filament\Actions\Action;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ViewAgencyRequest extends ViewRecord
{
    protected static string $resource = AgencyRequestResource::class;

    protected static ?string $title = 'مشاهده درخواست نمایندگی';

    public function infolist(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('مشخصات فنی')
                ->icon('heroicon-o-user')
                ->schema([
                    TextEntry::make('first_name')
                        ->label('نام'),
                    TextEntry::make('last_name')
                        ->label('نام خانوادگی'),
                    TextEntry::make('birth_date')
                        ->label('تاریخ تولد'),
                    TextEntry::make('email')
                        ->label('پست الکترونیک'),
                    TextEntry::make('phone')
                        ->label('تلفن'),
                    TextEntry::make('mobile')
                        ->label('تلفن همراه'),
                    TextEntry::make('father_name')
                        ->label('نام پدر'),
                    TextEntry::make('father_job')
                        ->label('شغل پدر'),
                    TextEntry::make('gender')
                        ->label('جنسیت')
                        ->formatStateUsing(fn (string $state): string => $state === 'male' ? 'مرد' : 'زن'),
                    TextEntry::make('marital_status')
                        ->label('وضعیت تاهل')
                        ->formatStateUsing(fn (string $state): string => $state === 'single' ? 'مجرد' : 'متاهل'),
                ])->columns(3),

            Section::make('استان اخذ نمایندگی')
                ->icon('heroicon-o-building-storefront')
                ->schema([
                    TextEntry::make('agencyProvince.name')
                        ->label('استان اخذ نمایندگی'),
                    TextEntry::make('agencyCity.name')
                        ->label('شهر اخذ نمایندگی'),
                ])->columns(2),

            Section::make('محل سکونت')
                ->icon('heroicon-o-home')
                ->schema([
                    TextEntry::make('residenceProvince.name')
                        ->label('استان'),
                    TextEntry::make('residenceCity.name')
                        ->label('شهر'),
                    TextEntry::make('neighborhood')
                        ->label('محله'),
                    TextEntry::make('address')
                        ->label('آدرس')
                        ->columnSpanFull(),
                ])->columns(3),

            Section::make('وضعیت درخواست')
                ->icon('heroicon-o-clock')
                ->schema([
                    TextEntry::make('status')
                        ->label('وضعیت')
                        ->formatStateUsing(fn (string $state): string => match ($state) {
                            'pending' => 'در انتظار بررسی',
                            'approved' => 'تایید شده',
                            'rejected' => 'رد شده',
                            default => $state,
                        })
                        ->badge(fn (string $state): string => match ($state) {
                            'approved' => 'success',
                            'rejected' => 'danger',
                            default => 'warning',
                        }),
                    TextEntry::make('created_at')
                        ->label('تاریخ ثبت')
                        ->dateTime()
                        ->jalaliDateTime('H:i Y/m/d'),
                ])->columns(2),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('markAsRead')
                ->label('علامت خوانده شده')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn (): bool => ! $this->record->is_read)
                ->action(function (): void {
                    $this->record->update(['is_read' => true]);
                    $this->refreshFormData(['is_read']);
                }),
            Action::make('approve')
                ->label('تایید درخواست')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->visible(fn (): bool => $this->record->status === 'pending')
                ->requiresConfirmation()
                ->action(function (): void {
                    $this->record->update(['status' => 'approved']);
                    $this->refreshFormData(['status']);
                }),
            Action::make('reject')
                ->label('رد درخواست')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn (): bool => $this->record->status === 'pending')
                ->requiresConfirmation()
                ->action(function (): void {
                    $this->record->update(['status' => 'rejected']);
                    $this->refreshFormData(['status']);
                }),
        ];
    }
}
