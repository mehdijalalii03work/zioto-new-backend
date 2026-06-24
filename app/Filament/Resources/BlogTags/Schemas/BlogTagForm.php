<?php

namespace App\Filament\Resources\BlogTags\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class BlogTagForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('اطلاعات تگ')
                    ->description('نام و شناسه تگ')
                    ->icon('heroicon-o-information-circle')
                    ->schema([
                        TextInput::make('name')
                            ->label('نام تگ')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true),

                        TextInput::make('slug')
                            ->label('شناسه')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->helperText('به صورت خودکار از نام تولید می‌شود'),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
