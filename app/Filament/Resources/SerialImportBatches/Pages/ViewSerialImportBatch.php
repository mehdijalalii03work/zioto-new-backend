<?php

namespace App\Filament\Resources\SerialImportBatches\Pages;

use App\Enums\Permission;
use App\Filament\Resources\SerialImportBatches\SerialImportBatchResource;
use App\Services\Inventory\SerialImportService;
use Filament\Actions\Action;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ViewSerialImportBatch extends ViewRecord
{
    protected static string $resource = SerialImportBatchResource::class;

    protected static ?string $title = 'مشاهده بچ ایمپورت';

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('نتیجه ایمپورت')
                    ->schema([
                        Grid::make(4)
                            ->schema($this->summaryEntries()),
                    ])
                    ->columnSpanFull(),

                Section::make('اطلاعات بچ')
                    ->collapsed()
                    ->schema([
                        TextEntry::make('original_filename')
                            ->label('فایل'),

                        TextEntry::make('status')
                            ->label('وضعیت')
                            ->formatStateUsing(fn ($state): string => $state->label())
                            ->badge(),

                        TextEntry::make('creator.name')
                            ->label('کاربر')
                            ->placeholder('سیستم'),

                        TextEntry::make('created_at')
                            ->label('تاریخ')
                            ->dateTime()
                            ->jalaliDateTime('H:i Y/m/d'),

                        TextEntry::make('last_error')
                            ->label('آخرین خطا')
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ])
                    ->columns(4)
                    ->columnSpanFull(),
            ]);
    }

    /** @return list<TextEntry> */
    private function summaryEntries(): array
    {
        $summary = app(SerialImportService::class)->summarize($this->record);

        $metrics = [
            'nonempty_rows' => 'سطرهای غیرخالی',
            'imported' => 'وارد شده',
            'duplicate_in_file' => 'تکراری در فایل',
            'duplicate_in_db' => 'تکراری در سامانه',
            'invalid_format' => 'قالب نامعتبر',
            'unknown_prefix' => 'پیشوند ناشناخته',
            'product_not_tracked' => 'محصول غیرسریالی',
            'pending' => 'در انتظار',
        ];

        $entries = [];
        foreach ($metrics as $key => $label) {
            $entries[] = TextEntry::make('summary_'.$key)
                ->label($label)
                ->state((string) ($summary[$key] ?? 0));
        }

        return $entries;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('downloadReport')
                ->label('دانلود گزارش کامل')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->authorize(Permission::SerialImport->value)
                ->url(fn (): string => route('serial-import.report', ['batch' => $this->record]))
                ->openUrlInNewTab(),
        ];
    }
}
