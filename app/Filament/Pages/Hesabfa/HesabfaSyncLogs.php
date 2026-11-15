<?php

namespace App\Filament\Pages\Hesabfa;

use App\Enums\Permission;
use App\Models\HesabfaSyncLog;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Livewire\Attributes\Url;

class HesabfaSyncLogs extends Page
{
    protected string $view = 'filament.pages.hesabfa-sync-logs';

    public static function canAccess(): bool
    {
        return auth()->user()?->can(Permission::HesabfaView->value) ?? false;
    }

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
        return 'لاگ حسابداری';
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return 'حسابداری';
    }

    public static function getNavigationSort(): ?int
    {
        return 2;
    }

    public function getTitle(): string
    {
        return 'لاگ همگام‌سازی حسابداری';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('deleteOldLogs')
                ->label('حذف لاگ‌های قدیمی')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->authorize(Permission::HesabfaSync->value)
                ->schema([
                    Select::make('days')
                        ->label('حذف لاگ‌های قدیمی‌تر از')
                        ->options([
                            30 => '۳۰ روز',
                            60 => '۶۰ روز',
                            90 => '۹۰ روز',
                            180 => '۱۸۰ روز',
                            365 => 'یک سال',
                        ])
                        ->default(90)
                        ->required(),
                ])
                ->modalHeading('حذف لاگ‌های قدیمی حسابداری')
                ->modalDescription('تمام لاگ‌های قدیمی‌تر از بازهٔ انتخاب‌شده حذف می‌شوند. این عملیات قابل بازگشت نیست.')
                ->modalSubmitActionLabel('حذف لاگ‌ها')
                ->requiresConfirmation()
                ->action(fn (array $data) => $this->deleteOldLogs((int) $data['days'])),
        ];
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
            'rahkaran_invoice' => 'info',
            'rahkaran_stock_sync' => 'warning',
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
            'rahkaran_invoice' => 'فاکتور راهکاران',
            'rahkaran_stock_sync' => 'موجودی و سریال راهکاران',
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

    public function goToPage(int $page): void
    {
        $lastPage = $this->getLogs()->lastPage();
        $this->page = max(1, min($page, $lastPage));
    }

    public function deleteOldLogs(int $days): void
    {
        abort_unless(auth()->user()?->can(Permission::HesabfaSync->value), 403);

        $days = max(30, min($days, 365));
        $deletedCount = HesabfaSyncLog::query()
            ->where('created_at', '<', now()->subDays($days))
            ->delete();

        $this->page = 1;

        Notification::make()
            ->title($deletedCount > 0 ? "{$deletedCount} لاگ حذف شد" : 'لاگی برای حذف پیدا نشد')
            ->success()
            ->send();
    }
}
