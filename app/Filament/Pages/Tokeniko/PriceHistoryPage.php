<?php

namespace App\Filament\Pages\Tokeniko;

use App\Models\PriceHistory;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Pagination\LengthAwarePaginator;
use Morilog\Jalali\Jalalian;

class PriceHistoryPage extends Page
{
    protected string $view = 'filament.pages.tokeniko.price-history';

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }

    public int $perPage = 30;

    public ?string $filterType = null;

    public ?string $dateFrom = null;

    public ?string $dateTo = null;

    public ?string $expandedId = null;

    protected mixed $recordsCache = null;

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-chart-bar';
    }

    public static function getNavigationLabel(): string
    {
        return 'تاریخچه قیمت‌ها';
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return 'سایر';
    }

    public static function getNavigationSort(): ?int
    {
        return 2;
    }

    public function getTitle(): string
    {
        return 'تاریخچه قیمت محصولات و تابلو';
    }

    public function getRecords(): LengthAwarePaginator
    {
        if ($this->recordsCache !== null) {
            return $this->recordsCache;
        }

        $query = PriceHistory::query()->orderBy('created_at', 'desc');

        if ($this->filterType) {
            $query->where('type', $this->filterType);
        }

        if ($this->dateFrom) {
            $query->whereDate('created_at', '>=', $this->resolveDate($this->dateFrom));
        }

        if ($this->dateTo) {
            $query->whereDate('created_at', '<=', $this->resolveDate($this->dateTo));
        }

        $this->recordsCache = $query->paginate($this->perPage);

        return $this->recordsCache;
    }

    public function setFilterType(?string $type): void
    {
        $this->filterType = $type;
        $this->recordsCache = null;
    }

    public function updatedDateFrom(): void
    {
        $this->recordsCache = null;
    }

    public function updatedDateTo(): void
    {
        $this->recordsCache = null;
    }

    public function clearFilters(): void
    {
        $this->filterType = null;
        $this->dateFrom = null;
        $this->dateTo = null;
        $this->recordsCache = null;
    }

    public function toggleExpand(string $id): void
    {
        $this->expandedId = $this->expandedId === $id ? null : $id;
    }

    public function pruneOldRecords(): void
    {
        $deleted = PriceHistory::where('created_at', '<', Carbon::now()->subDays(30))->delete();

        Notification::make()
            ->title("{$deleted} رکورد قدیمی حذف شد")
            ->success()
            ->send();
    }

    public function formatPrice(int|float $price): string
    {
        return number_format((int) $price);
    }

    public function getTypeLabel(string $type): string
    {
        return match ($type) {
            'board' => 'تابلو قیمت',
            'product' => 'قیمت محصول',
            default => $type,
        };
    }

    public function getTypeColor(string $type): string
    {
        return match ($type) {
            'board' => 'bg-info-50 text-info-700 ring-info-600/20 dark:bg-info-500/10 dark:text-info-500 dark:ring-info-500/30',
            'product' => 'bg-success-50 text-success-700 ring-success-600/20 dark:bg-success-500/10 dark:text-success-500 dark:ring-success-500/30',
            default => 'bg-gray-50 text-gray-700 ring-gray-600/20 dark:bg-gray-500/10 dark:text-gray-400 dark:ring-gray-500/30',
        };
    }

    public function getBoardItems(array $data): array
    {
        return $data['products'] ?? [];
    }

    public function getProductItems(array $data): array
    {
        return $data;
    }

    private function resolveDate(string $date): ?string
    {
        try {
            if (str_contains($date, '/')) {
                $parts = explode('/', $date);

                if (count($parts) === 3 && (int) $parts[0] < 1700) {
                    return Jalalian::fromFormat('Y/m/d', $date)->toCarbon()->format('Y-m-d');
                }
            }

            return Carbon::parse($date)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }
}
