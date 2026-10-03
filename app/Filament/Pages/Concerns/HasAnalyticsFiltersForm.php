<?php

namespace App\Filament\Pages\Concerns;

use App\Enums\AnalyticsPeriod;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

/**
 * Adds the shared "reporting window" filter to a page. Traffic widgets read the
 * resulting values through `Filament\Widgets\Concerns\InteractsWithPageFilters`.
 */
trait HasAnalyticsFiltersForm
{
    use HasFiltersForm;

    public function filtersForm(Schema $schema): Schema
    {
        [$defaultFrom, $defaultTo] = AnalyticsPeriod::Last7Days->defaultCustomBoundaries();

        return $schema
            ->components([
                Select::make('period')
                    ->label('بازه گزارش')
                    ->options(AnalyticsPeriod::options())
                    ->default(AnalyticsPeriod::Last30Days->value)
                    ->selectablePlaceholder(false)
                    ->live(),

                DatePicker::make('from')
                    ->label('از تاریخ')
                    ->jalali()
                    ->native(false)
                    ->displayFormat('Y/m/d')
                    ->default($defaultFrom)
                    ->visible(fn (Get $get): bool => $get('period') === AnalyticsPeriod::Custom->value),

                DatePicker::make('to')
                    ->label('تا تاریخ')
                    ->jalali()
                    ->native(false)
                    ->displayFormat('Y/m/d')
                    ->default($defaultTo)
                    ->visible(fn (Get $get): bool => $get('period') === AnalyticsPeriod::Custom->value),
            ]);
    }
}
