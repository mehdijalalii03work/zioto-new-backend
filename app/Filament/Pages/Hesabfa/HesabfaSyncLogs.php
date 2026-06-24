<?php

namespace App\Filament\Pages\Hesabfa;

use App\Models\HesabfaSyncLog;
use Filament\Pages\Page;
use Livewire\Attributes\Url;

class HesabfaSyncLogs extends Page
{
    protected string $view = 'filament.pages.hesabfa-sync-logs';

    #[Url]
    public int $page = 1;

    public int $perPage = 15;

    public ?string $filterType = null;

    public ?string $filterStatus = null;

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-document-text';
    }

    public static function getNavigationLabel(): string
    {
        return 'لاگ همگام‌سازی';
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return 'حسابفا';
    }

    public static function getNavigationSort(): ?int
    {
        return 3;
    }

    public function getTitle(): string
    {
        return 'لاگ همگام‌سازی حسابفا';
    }

    public function getLogs()
    {
        $query = HesabfaSyncLog::with('order')->latest();

        if ($this->filterType) {
            $query->where('sync_type', $this->filterType);
        }

        if ($this->filterStatus) {
            $query->where('status', $this->filterStatus);
        }

        return $query->paginate($this->perPage, ['*'], 'page', $this->page);
    }

    public function getSyncTypeBadge(string $type): string
    {
        return match ($type) {
            'full_sync' => 'success',
            'contact' => 'info',
            'invoice' => 'warning',
            default => 'gray',
        };
    }

    public function getStatusBadge(string $status): string
    {
        return match ($status) {
            'success' => 'success',
            'failed' => 'danger',
            default => 'gray',
        };
    }

    public function getSyncTypeLabel(string $type): string
    {
        return match ($type) {
            'full_sync' => 'همگام‌سازی کامل',
            'contact' => 'مشتری',
            'invoice' => 'فاکتور',
            default => $type,
        };
    }

    public function getStatusLabel(string $status): string
    {
        return match ($status) {
            'success' => 'موفق',
            'failed' => 'ناموفق',
            default => $status,
        };
    }

    public function setFilterType(?string $type): void
    {
        $this->filterType = $type;
        $this->page = 1;
    }

    public function setFilterStatus(?string $status): void
    {
        $this->filterStatus = $status;
        $this->page = 1;
    }
}
