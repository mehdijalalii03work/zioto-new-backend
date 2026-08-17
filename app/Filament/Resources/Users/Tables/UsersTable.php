<?php

namespace App\Filament\Resources\Users\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('شناسه')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('first_name')
                    ->label('نام')
                    ->searchable()
                    ->default('-')
                    ->sortable(),

                TextColumn::make('last_name')
                    ->label('نام خانوادگی')
                    ->searchable()
                    ->default('-')
                    ->sortable(),

                TextColumn::make('phone')
                    ->label('موبایل')
                    ->searchable()
                    ->default('-')
                    ->sortable(),

                TextColumn::make('national_code')
                    ->label('کد ملی')
                    ->searchable()
                    ->default('-')
                    ->toggleable(),

                TextColumn::make('email')
                    ->label('ایمیل')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('shahkar_verified')
                    ->label('احراز هویت')
                    ->boolean()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('platform')
                    ->label('پلتفرم')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'main' => 'info',
                        'nopay' => 'success',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'main' => 'زیوتو',
                        'nopay' => 'نوپی',
                        default => $state ?? '—',
                    })
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('roles.name')
                    ->label('نقش')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label('تاریخ ثبت‌نام')
                    ->jalaliDateTime('H:i Y/m/d')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('roles')
                    ->label('نقش')
                    ->relationship('roles', 'name')
                    ->searchable()
                    ->multiple(),
                SelectFilter::make('platform')
                    ->label('پلتفرم')
                    ->options([
                        'main' => 'زیوتو',
                        'nopay' => 'نوپی',
                    ]),
                TrashedFilter::make(),
            ])
            ->defaultSort('created_at', 'desc')
            ->defaultPaginationPageOption(25)
            ->recordActions([
                ViewAction::make()
                    ->label('نمایش'),
                EditAction::make()
                    ->label('ویرایش')
                    ->visible(fn ($record) => is_null($record->deleted_at)),
                DeleteAction::make()
                    ->label('حذف')
                    ->visible(fn ($record) => is_null($record->deleted_at)),
                RestoreAction::make()
                    ->label('بازیابی')
                    ->visible(fn ($record) => filled($record->deleted_at)),
                ForceDeleteAction::make()
                    ->label('حذف دائمی')
                    ->visible(fn ($record) => filled($record->deleted_at)),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('حذف انتخاب شده‌ها')
                        ->authorizeIndividualRecords(),
                    RestoreBulkAction::make()
                        ->label('بازیابی انتخاب شده‌ها')
                        ->authorizeIndividualRecords(),
                    ForceDeleteBulkAction::make()
                        ->label('حذف دائمی انتخاب شده‌ها')
                        ->authorizeIndividualRecords(),
                ]),
            ]);
    }
}
