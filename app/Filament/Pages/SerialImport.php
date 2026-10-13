<?php

namespace App\Filament\Pages;

use App\Enums\Permission;
use App\Models\SerialImportBatch;
use App\Services\Inventory\SerialImportException;
use App\Services\Inventory\SerialImportReportService;
use App\Services\Inventory\SerialImportService;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Modules\Product\Models\Product;

class SerialImport extends Page
{
    protected string $view = 'filament.pages.serial-import';

    protected static ?string $title = 'ایمپورت سریال';

    protected static ?string $navigationLabel = 'ایمپورت سریال';

    protected static string|\UnitEnum|null $navigationGroup = 'مدیریت کالا';

    protected static ?int $navigationSort = 11;

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-qr-code';
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->checkPermissionTo(Permission::SerialImport->value) ?? false;
    }

    public array $data = [];

    #[Url]
    #[Locked]
    public ?int $batch = null;

    /** @var array<string, mixed>|null */
    #[Locked]
    public ?array $summary = null;

    /** @var list<array<string, mixed>> */
    #[Locked]
    public array $preview = [];

    #[Locked]
    public ?string $hashWarning = null;

    #[Locked]
    public array $worksheetOptions = [];

    public ?string $selectedWorksheet = null;

    public function mount(): void
    {
        $this->form->fill();

        if ($this->batch !== null) {
            $this->loadBatch($this->batch);
        }
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                FileUpload::make('upload')
                    ->label('فایل اکسل کارخانه (XLSX)')
                    ->helperText('فقط ستون M با عنوان «عامل اول» خوانده می‌شود؛ بقیه ستون‌ها نادیده گرفته می‌شوند.')
                    ->disk('local')
                    ->directory('serial-imports')
                    ->acceptedFileTypes(['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])
                    ->maxSize(10 * 1024)
                    ->storeFileNamesIn('upload_original_name')
                    ->required(),
            ])
            ->statePath('data');
    }

    public function analyzeUpload(): void
    {
        abort_unless(static::canAccess(), 403);
        $state = $this->form->getState();

        $diskPath = is_array($state['upload'] ?? null) ? null : ($state['upload'] ?? null);

        if (! is_string($diskPath) || $diskPath === '') {
            Notification::make()->title('ابتدا فایل اکسل را انتخاب کنید.')->danger()->send();

            return;
        }

        $originalName = $state['upload_original_name'] ?? basename($diskPath);

        $this->analyzeStoredFile($diskPath, is_string($originalName) ? $originalName : basename($diskPath));
    }

    public function analyzeStoredFile(string $diskPath, string $originalName): void
    {
        abort_unless(static::canAccess(), 403);
        abort_unless(preg_match('~\Aserial-imports/[a-zA-Z0-9_-]+\.xlsx\z~i', $diskPath) === 1, 403);
        $absolutePath = Storage::disk('local')->path($diskPath);
        $root = realpath(Storage::disk('local')->path('serial-imports'));
        $resolved = realpath($absolutePath);
        abort_unless($root !== false && $resolved !== false && str_starts_with($resolved, $root.DIRECTORY_SEPARATOR), 403);
        $service = app(SerialImportService::class);
        try {
            $options = $service->matchingWorksheets($resolved);
            $batch = $service->createBatch($resolved, basename($originalName), auth()->id());
            $this->batch = $batch->id;
            $this->selectedWorksheet = null;
            $this->worksheetOptions = $options;
            if (count($options) === 1) {
                $batch->update(['worksheet_name' => $options[0]]);
                $service->dispatchAnalyze($batch);
            } elseif (count($options) > 1) {
                $batch->update(['status' => 'failed', 'last_error' => 'کاربرگ را انتخاب کنید.']);
            } else {
                throw SerialImportException::fileError('عنوان ستون سریال در فایل یافت نشد.');
            }
            $this->loadBatch($batch->id);
        } catch (\Throwable $e) {
            report($e);
            Notification::make()->title(SerialImportException::publicMessage($e))->danger()->send();
        }
    }

    public function selectWorksheet(): void
    {
        abort_unless(static::canAccess(), 403);
        $batch = $this->getBatchRecord();
        abort_unless($batch !== null && $batch->status->value === 'failed' && ! $batch->rows()->exists(), 403);
        try {
            $options = app(SerialImportService::class)->matchingWorksheets($batch->file_path);
        } catch (\Throwable $exception) {
            report($exception);
            $message = SerialImportException::publicMessage($exception);
            $batch->update(['status' => 'failed', 'last_error' => $message, 'completed_at' => now()]);
            $this->worksheetOptions = [];
            $this->loadBatch($batch->id);
            Notification::make()->title($message)->danger()->send();

            return;
        }
        abort_unless(in_array($this->selectedWorksheet, $options, true), 422);
        $batch->update(['worksheet_name' => $this->selectedWorksheet]);
        app(SerialImportService::class)->dispatchAnalyze($batch);
        $this->worksheetOptions = [];
        $this->loadBatch($batch->id);
    }

    public function refreshProgress(): void
    {
        abort_unless(static::canAccess(), 403);
        if ($this->batch !== null) {
            $this->loadBatch($this->batch);
        }
    }

    public function runImport(): void
    {
        abort_unless(static::canAccess(), 403);
        $batch = $this->getBatchRecord();
        if ($batch === null) {
            return;
        }
        try {
            app(SerialImportService::class)->dispatchImport($batch);
        } catch (SerialImportException $e) {
            report($e);
            Notification::make()->title(SerialImportException::publicMessage($e))->danger()->send();
        }
        $this->loadBatch($batch->id);
    }

    public function getBatchRecord(): ?SerialImportBatch
    {
        if ($this->batch === null) {
            return null;
        }

        return SerialImportBatch::find($this->batch);
    }

    public function getReportUrl(): ?string
    {
        if ($this->batch === null) {
            return null;
        }

        return route('serial-import.report', ['batch' => $this->batch]);
    }

    private function loadBatch(int $batchId): void
    {
        abort_unless(static::canAccess(), 403);
        $batch = SerialImportBatch::findOrFail($batchId);
        if ($batch->status->value === 'failed' && $batch->worksheet_name === null && ! $batch->rows()->exists() && is_file((string) $batch->file_path)) {
            try {
                $this->worksheetOptions = app(SerialImportService::class)->matchingWorksheets($batch->file_path);
            } catch (\Throwable $exception) {
                report($exception);
                $this->worksheetOptions = [];
                Notification::make()->title(SerialImportException::publicMessage($exception))->danger()->send();
            }
        }
        $service = app(SerialImportService::class);

        $this->summary = $service->summarize($batch);
        $this->hashWarning = $this->findHashWarning($batch);

        $products = Product::whereIn(
            'id',
            $batch->rows()->whereNotNull('product_id')->pluck('product_id')->unique()->all(),
        )->get(['id', 'name', 'sku'])->keyBy('id');

        $this->preview = $batch->rows()
            ->orderBy('row_number')
            ->limit(20)
            ->get()
            ->map(fn ($row): array => [
                'row_number' => $row->row_number,
                'raw_value' => $row->raw_value,
                'normalized_serial' => $row->normalized_serial,
                'product' => $row->product_id !== null && $products->has($row->product_id)
                    ? $products->get($row->product_id)->name
                    : '—',
                'validation' => SerialImportReportService::validationLabel($row->validation_status->value),
                'result' => SerialImportReportService::resultLabel($row->result_status->value),
                'reason' => SerialImportReportService::reasonLabel($row),
            ])
            ->all();
    }

    private function findHashWarning(SerialImportBatch $batch): ?string
    {
        $previous = SerialImportBatch::where('file_hash', $batch->file_hash)
            ->where('id', '!=', $batch->id)
            ->latest()
            ->first();

        if ($previous === null) {
            return null;
        }

        return "این فایل قبلاً در بچ #{$previous->id} ({$previous->original_filename}) بررسی شده است؛ سریال‌های تکراری دوباره ثبت نمی‌شوند.";
    }
}
