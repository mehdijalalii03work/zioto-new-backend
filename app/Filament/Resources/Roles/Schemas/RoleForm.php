<?php

namespace App\Filament\Resources\Roles\Schemas;

use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class RoleForm
{
    public static function sections(): array
    {
        return [
            Section::make('اطلاعات نقش')
                ->description('نام نقش و دسترسی‌ها')
                ->icon('heroicon-o-shield-check')
                ->collapsible()
                ->schema([
                    Grid::make(1)
                        ->schema([
                            TextInput::make('name')
                                ->label('نام نقش')
                                ->required()
                                ->unique(ignoreRecord: true)
                                ->maxLength(50),

                            CheckboxList::make('permissions')
                                ->relationship('permissions', 'name')
                                ->label('دسترسی‌ها')
                                ->columns(3)
                                ->searchable()
                                ->bulkToggleable(),
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
