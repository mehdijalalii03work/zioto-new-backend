<?php

namespace App\Filament\Pages;

use App\Enums\Permission;
use App\Services\OfflineInventoryImport;
use App\Support\InventorySource;
use DomainException;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Livewire\Attributes\Locked;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class ReceiveInventory extends Page
{
    protected static ?string $slug = 'receive-inventory';

    protected static ?string $title = 'ثبت ورود موجودی';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrow-down-tray';

    protected static string|\UnitEnum|null $navigationGroup = 'حسابداری';

    protected string $view = 'filament.pages.receive-inventory';

    public array $data = [];

    #[Locked]
    public array $previewItems = [];

    #[Locked]
    public array $previewErrors = [];

    #[Locked]
    public ?string $previewHash = null;

    public static function canAccess(): bool
    {
        return InventorySource::isOffline() && (auth()->user()?->can(Permission::HesabfaSync->value) ?? false);
    }

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('document_number')->label('شماره سند انتقال')->maxLength(100)->required(),
            FileUpload::make('workbook')
                ->label('فایل اکسل ورود به انبار فروشگاه')
                ->acceptedFileTypes(['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])
                ->maxSize(5120)
                ->storeFiles(false)
                ->required(),
        ])->statePath('data');
    }

    public function preview(OfflineInventoryImport $import): void
    {
        abort_unless(static::canAccess(), 403);
        $this->clearPreview();
        $data = $this->form->getState();

        try {
            $preview = $import->preview($this->path($data['workbook']), $data['document_number']);
            $this->previewItems = array_map(fn (array $item): array => [
                'name' => $item['name'], 'code' => $item['code'], 'quantity' => $item['quantity'], 'duplicates' => $item['duplicates'],
            ], $preview['items']);
            $this->previewErrors = $preview['errors'];
            $this->previewHash = $preview['errors'] === [] ? $preview['hash'] : null;
        } catch (DomainException $exception) {
            $this->previewErrors = [$exception->getMessage()];
        }
    }

    public function receive(OfflineInventoryImport $import): void
    {
        abort_unless(static::canAccess(), 403);
        if ($this->previewHash === null) {
            return;
        }

        $data = $this->form->getState();
        try {
            $items = $import->receive($this->path($data['workbook']), $data['document_number'], (int) auth()->id(), $this->previewHash);
            $this->clearPreview();
            $this->form->fill();
            Notification::make()
                ->title('ورود موجودی ثبت شد')
                ->body('تعداد واحدهای اضافه‌شده: '.array_sum(array_column($items, 'quantity')))
                ->success()->send();
        } catch (DomainException $exception) {
            $this->clearPreview();
            $this->previewErrors = [$exception->getMessage()];
        }
    }

    private function path(mixed $file): string
    {
        if (! $file instanceof TemporaryUploadedFile) {
            throw new DomainException('فایل اکسل را دوباره بارگذاری کنید.');
        }

        return $file->getRealPath();
    }

    private function clearPreview(): void
    {
        $this->previewHash = null;
        $this->previewItems = [];
        $this->previewErrors = [];
    }
}
