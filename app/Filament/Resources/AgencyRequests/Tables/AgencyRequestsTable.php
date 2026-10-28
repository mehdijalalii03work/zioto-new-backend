<?php

namespace App\Filament\Resources\AgencyRequests\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AgencyRequestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('#')
                    ->sortable(),

                TextColumn::make('first_name')
                    ->label('نام')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('last_name')
                    ->label('نام خانوادگی')
                    ->searchable()
                    ->sortable()
                    ->url(fn ($record): string => route('filament.admin.resources.agency-requests.view', $record)),

                TextColumn::make('mobile')
                    ->label('تلفن همراه')
                    ->searchable(),

                TextColumn::make('agencyProvince.name')
                    ->label('استان نمایندگی')
                    ->sortable(),

                TextColumn::make('agencyCity.name')
                    ->label('شهر نمایندگی'),

                TextColumn::make('status')
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
                    })
                    ->sortable(),

                IconColumn::make('is_read')
                    ->label('خوانده شده')
                    ->boolean()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('تاریخ ثبت')
                    ->dateTime()
                    ->jalaliDateTime('H:i Y/m/d')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->defaultPaginationPageOption(25)
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make()
                    ->label('مشاهده'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('حذف انتخاب شده‌ها'),
                ]),
            ]);
    }
}
