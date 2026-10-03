<?php

namespace App\Filament\Widgets\Traffic;

use App\Filament\Widgets\Concerns\InteractsWithAnalyticsPeriod;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Collection;

/**
 * The most viewed pages, with each page's share of all page views.
 */
class TopPagesTable extends TableWidget
{
    use InteractsWithAnalyticsPeriod;

    protected int|string|array $columnSpan = ['@lg' => 2, '@2xl' => 3];

    protected const LIMIT = 15;

    protected ?Collection $pages = null;

    public function table(Table $table): Table
    {
        return $table
            ->heading('پرصفحه‌ترین صفحات')
            ->description($this->tableDescription())
            ->columns([
                TextColumn::make('rank')
                    ->label('#')
                    ->alignCenter()
                    ->color('gray'),

                TextColumn::make('title')
                    ->label('صفحه')
                    ->wrap()
                    ->description(fn (array $record): ?string => filled($record['url']) ? $record['url'] : null),

                TextColumn::make('pageViews')
                    ->label('بازدید')
                    ->alignEnd()
                    ->formatStateUsing(fn (int $state): string => number_format($state)),

                TextColumn::make('share')
                    ->label('سهم')
                    ->alignEnd()
                    ->badge()
                    ->formatStateUsing(fn (float $state): string => number_format($state, 1).'٪'),
            ])
            ->paginated(false)
            ->emptyStateHeading($this->emptyStateHeading())
            ->emptyStateDescription($this->emptyStateDescription())
            ->emptyStateIcon('heroicon-o-document-chart-bar');
    }

    /**
     * @return Collection<int, array{__key: string, rank: int, title: string, url: string, pageViews: int, share: float}>
     */
    public function getTableRecords(): Collection
    {
        return $this->pages();
    }

    /**
     * @return Collection<int, array{__key: string, rank: int, title: string, url: string, pageViews: int, share: float}>
     */
    protected function pages(): Collection
    {
        if ($this->pages instanceof Collection) {
            return $this->pages;
        }

        if (! $this->isAnalyticsAvailable()) {
            return $this->pages = collect();
        }

        $rows = $this->analyticsService()
            ->topPages($this->analyticsWindow(), self::LIMIT)
            ->filter(fn (array $row): bool => $row['pageViews'] > 0)
            ->values();

        $total = (int) $rows->sum('pageViews');

        return $this->pages = $rows
            ->map(fn (array $row, int $index): array => [
                ...$row,
                '__key' => 'page-'.($index + 1),
                'rank' => $index + 1,
                'share' => $total > 0 ? round(($row['pageViews'] / $total) * 100, 1) : 0.0,
            ])
            ->values();
    }

    private function tableDescription(): ?string
    {
        if (! $this->isAnalyticsAvailable()) {
            return $this->unavailableDescription();
        }

        return 'بازه '.$this->analyticsWindowLabel();
    }
}
