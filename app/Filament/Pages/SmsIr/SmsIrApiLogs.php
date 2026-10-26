<?php

namespace App\Filament\Pages\SmsIr;

use App\Models\SmsIrApiLog;
use Filament\Pages\Page;
use Illuminate\Pagination\LengthAwarePaginator;

class SmsIrApiLogs extends Page
{
    protected string $view = 'filament.pages.sms-ir-api-logs';

    public int $perPage = 20;

    public ?string $filterStatus = null;

    public ?string $filterMobile = null;

    public ?SmsIrApiLog $selectedLog = null;

    public bool $showDetailModal = false;

    protected mixed $logsCache = null;

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-chat-bubble-left-right';
    }

    public static function getNavigationLabel(): string
    {
        return 'لاگ‌های پیامک';
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
        return 'لاگ درخواست‌های SMS.ir';
    }

    public function getLogs(): LengthAwarePaginator
    {
        if ($this->logsCache !== null) {
            return $this->logsCache;
        }

        $query = $this->newLogsQuery();

        if ($this->filterStatus !== null) {
            $query->where('success', $this->filterStatus === 'success');
        }

        if ($this->filterMobile) {
            $query->where('mobile', 'like', '%'.$this->filterMobile.'%');
        }

        $this->logsCache = $query->paginate($this->perPage);

        return $this->logsCache;
    }

    protected function newLogsQuery()
    {
        return SmsIrApiLog::query()->orderBy('created_at', 'desc');
    }

    public function setFilterStatus(?string $status): void
    {
        $this->filterStatus = $status;
        $this->logsCache = null;
    }

    public function updatedFilterMobile(): void
    {
        $this->logsCache = null;
    }

    public function viewLog(string $logId): void
    {
        $this->selectedLog = SmsIrApiLog::find($logId);
        $this->showDetailModal = $this->selectedLog !== null;
    }

    public function closeDetailModal(): void
    {
        $this->showDetailModal = false;
        $this->selectedLog = null;
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

    public function formatJson(mixed $data): string
    {
        if ($data === null) {
            return '—';
        }

        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: '—';
    }
}
