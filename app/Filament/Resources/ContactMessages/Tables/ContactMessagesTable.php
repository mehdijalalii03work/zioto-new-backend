<?php

namespace App\Filament\Resources\ContactMessages\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ContactMessagesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('#')
                    ->sortable(),

                TextColumn::make('name')
                    ->label('نام')
                    ->searchable()
                    ->sortable()
                    ->url(fn ($record): string => route('filament.admin.resources.contact-messages.view', $record)),

                TextColumn::make('phone')
                    ->label('شماره تماس')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('subject')
                    ->label('موضوع')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('message')
                    ->label('متن پیام')
                    ->limit(50)
                    ->tooltip(fn ($state): string => $state),

                IconColumn::make('is_read')
                    ->label('خوانده شده')
                    ->boolean()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('تاریخ ارسال')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
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
