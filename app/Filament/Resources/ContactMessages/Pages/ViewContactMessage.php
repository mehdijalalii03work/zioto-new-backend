<?php

namespace App\Filament\Resources\ContactMessages\Pages;

use App\Filament\Resources\ContactMessages\ContactMessageResource;
use Filament\Actions\Action;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;

class ViewContactMessage extends ViewRecord
{
    protected static string $resource = ContactMessageResource::class;

    protected static ?string $title = 'مشاهده پیام';

    public function infolist(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('اطلاعات فرستنده')
                ->icon('heroicon-o-user')
                ->schema([
                    TextEntry::make('name')
                        ->label('نام و نام خانوادگی'),
                    TextEntry::make('phone')
                        ->label('شماره تماس'),
                    TextEntry::make('subject')
                        ->label('موضوع')
                        ->placeholder('بدون موضوع'),
                ])->columns(3),

            Section::make('متن پیام')
                ->icon('heroicon-o-chat-bubble-left')
                ->schema([
                    TextEntry::make('message')
                        ->label('متن پیام')
                        ->columnSpanFull()
                        ->prose(),
                ]),

            Section::make('اطلاعات ارسال')
                ->icon('heroicon-o-clock')
                ->schema([
                    TextEntry::make('created_at')
                        ->label('تاریخ ارسال')
                        ->dateTime(),
                    TextEntry::make('is_read')
                        ->label('وضعیت خواندن')
                        ->formatStateUsing(fn (bool $state): string => $state ? 'خوانده شده' : 'خوانده نشده')
                        ->badge(fn (bool $state): string => $state ? 'success' : 'warning'),
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
        ];
    }
}
