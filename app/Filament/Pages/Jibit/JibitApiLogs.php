<?php

namespace App\Filament\Pages\Jibit;

use App\Models\JibitApiLog;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Livewire\Attributes\Url;

class JibitApiLogs extends Page
{
    protected string $view = 'filament.pages.jibit-api-logs';

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }

    #[Url]
    public int $page = 1;

    public int $perPage = 20;

    public ?string $filterEndpoint = null;

    public ?string $filterStatus = null;

    public ?string $selectedLogId = null;

    public ?JibitApiLog $selectedLog = null;

    public bool $showDetailModal = false;

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-server-stack';
    }

    public static function getNavigationLabel(): string
    {
        return 'لاگ‌های جیبیت';
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return 'جیبیت';
    }

    public static function getNavigationSort(): ?int
    {
        return 1;
    }

    public function getTitle(): string
    {
        return 'لاگ درخواست‌های جیبیت';
    }

    public function getLogs()
    {
        $query = JibitApiLog::query()->latest();

        if ($this->filterEndpoint) {
            $query->where('endpoint', $this->filterEndpoint);
        }

        if ($this->filterStatus !== null) {
            $query->where('success', $this->filterStatus === 'success');
        }

        return $query->paginate($this->perPage, ['*'], 'page', $this->page);
    }

    public function setFilterEndpoint(?string $endpoint): void
    {
        $this->filterEndpoint = $endpoint;
        $this->page = 1;
    }

    public function setFilterStatus(?string $status): void
    {
        $this->filterStatus = $status;
        $this->page = 1;
    }

    public function viewLog(int $logId): void
    {
        $this->selectedLog = JibitApiLog::find($logId);
        $this->showDetailModal = true;
    }

    public function closeDetailModal(): void
    {
        $this->showDetailModal = false;
        $this->selectedLog = null;
    }

    public function pruneOldLogs(): void
    {
        $deleted = JibitApiLog::where('created_at', '<', Carbon::now()->subDays(30))->delete();

        Notification::make()
            ->title("{$deleted} لاگ قدیمی حذف شد")
            ->success()
            ->send();
    }

    public function getEndpointLabel(string $endpoint): string
    {
        return match ($endpoint) {
            '/v1/tokens/generate' => 'تولید توکن',
            '/v1/services/matching' => 'احراز هویت (تطبیق)',
            '/v1/services/identity' => 'اطلاعات هویتی',
            default => $endpoint,
        };
    }

    public function getMethodColor(string $method): string
    {
        return match ($method) {
            'GET' => 'bg-info-50 text-info-700 ring-info-600/20 dark:bg-info-500/10 dark:text-info-500 dark:ring-info-500/30',
            'POST' => 'bg-success-50 text-success-700 ring-success-600/20 dark:bg-success-500/10 dark:text-success-500 dark:ring-success-500/30',
            default => 'bg-gray-50 text-gray-700 ring-gray-600/20 dark:bg-gray-500/10 dark:text-gray-400 dark:ring-gray-500/30',
        };
    }

    public function getStatusColor(bool $success): string
    {
        return $success
            ? 'bg-success-50 text-success-700 ring-success-600/20 dark:bg-success-500/10 dark:text-success-500 dark:ring-success-500/30'
            : 'bg-danger-50 text-danger-700 ring-danger-600/20 dark:bg-danger-500/10 dark:text-danger-500 dark:ring-danger-500/30';
    }

    public function getStatusLabel(bool $success): string
    {
        return $success ? 'موفق' : 'ناموفق';
    }

    public function formatJson(?array $data): string
    {
        if ($data === null) {
            return '—';
        }

        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
}
