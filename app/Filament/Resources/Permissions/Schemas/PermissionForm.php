<?php

namespace App\Filament\Resources\Permissions\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PermissionForm
{
    public static function sections(): array
    {
        return [
            Section::make('اطلاعات دسترسی')
                ->description('نام دسترسی')
                ->icon('heroicon-o-key')
                ->collapsible()
                ->schema([
                    Grid::make(1)
                        ->schema([
                            TextInput::make('name')
                                ->label('نام دسترسی')
                                ->required()
                                ->unique(ignoreRecord: true)
                                ->maxLength(50),
                        ]),
                ])
                ->columnSpanFull(),
        ];
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components(static::sections());
    }
}
