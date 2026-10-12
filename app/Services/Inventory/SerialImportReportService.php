<?php

namespace App\Services\Inventory;

use App\Enums\Inventory\SerialRowResultStatus;
use App\Enums\Inventory\SerialRowValidationStatus;
use App\Models\SerialImportBatch;
use App\Models\SerialImportRow;
use Illuminate\Support\Facades\Response;
use Modules\Product\Models\Product;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Bounded-memory three-sheet export with explicit string cells.
 */
final class SerialImportReportService
{
    public function __construct(private readonly SerialImportService $imports) {}

    public function download(SerialImportBatch $batch): StreamedResponse
    {
        $filename = 'serial-import-'.$batch->id.'-'.now()->format('Y-m-d-H-i').'.xlsx';

        return Response::stream(function () use ($batch): void {
            $this->writeToFile($batch->fresh(), 'php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment;filename=\"{$filename}\"",
            'Cache-Control' => 'max-age=0',
        ]);
    }

    public function writeToFile(SerialImportBatch $batch, string $path): void
    {
        $writer = new Writer;
        $writer->openToFile($path);

        try {
            $summary = $this->imports->summarize($batch);
            $this->configureSheet($writer, 'خلاصه', [28, 48]);
            $this->writeSummary($writer, $batch, $summary);
            $writer->addNewSheetAndMakeItCurrent();
            $this->configureSheet($writer, 'همه ردیف‌ها', [12, 24, 24, 12, 40, 18, 18, 44]);
            $this->writeStrings($writer, ['ردیف شیت', 'مقدار خام', 'نرمال‌شده', 'پیشوند', 'محصول', 'وضعیت تحلیل', 'نتیجه نهایی', 'دلیل']);

            foreach (SerialImportRow::where('import_batch_id', $batch->id)->with('product:id,name,sku')->lazyById(500, 'row_number') as $row) {
                $product = $row->product;
                $this->writeStrings($writer, [
                    (string) $row->row_number,
                    (string) ($row->raw_value ?? ''),
                    (string) ($row->normalized_serial ?? ''),
                    (string) ($row->resolved_prefix ?? ''),
                    $product ? $product->name.' ('.$product->sku.')' : '',
                    self::validationLabel($row->validation_status->value),
                    self::resultLabel($row->result_status->value),
                    self::reasonLabel($row),
                ]);
            }

            $writer->addNewSheetAndMakeItCurrent();
            $this->configureSheet($writer, 'تفکیک محصولات', [44, 14, 16]);
            $this->writeStrings($writer, ['محصول', 'پیشوند', 'تعداد واردشده']);
            foreach (array_chunk($summary['per_product'], 500, true) as $counts) {
                $products = Product::whereIn('id', array_keys($counts))->get(['id', 'name', 'sku', 'serial_prefix'])->keyBy('id');
                foreach ($counts as $productId => $count) {
                    $product = $products->get($productId);
                    $this->writeStrings($writer, [$product ? $product->name.' ('.$product->sku.')' : 'شناسه '.$productId, (string) ($product?->serial_prefix ?? ''), (string) $count]);
                }
            }
        } finally {
            $writer->close();
        }
    }

    /** @param list<int> $widths */
    private function configureSheet(Writer $writer, string $name, array $widths): void
    {
        $sheet = $writer->getCurrentSheet();
        $sheet->setName($name);
        $sheet->setSheetView((new SheetView)->setRightToLeft(true));
        foreach ($widths as $index => $width) {
            $sheet->setColumnWidth($width, $index + 1);
        }
    }

    /** @param list<string> $values */
    private function writeStrings(Writer $writer, array $values): void
    {
        $writer->addRow(new Row(array_map(fn (string $value): StringCell => new StringCell(mb_substr($value, 0, 32767, 'UTF-8'), null), $values)));
    }

    /** @param array<string, mixed> $summary */
    private function writeSummary(Writer $writer, SerialImportBatch $batch, array $summary): void
    {
        $this->writeStrings($writer, ['شاخص', 'مقدار']);
        $lines = [
            ['فایل', (string) $batch->original_filename],
            ['وضعیت', $batch->status->label()],
            ['تاریخ تحلیل', $batch->started_at?->format('Y/m/d H:i') ?? '—'],
            ['تاریخ پایان', $batch->completed_at?->format('Y/m/d H:i') ?? '—'],
            ['سطرهای غیرخالی', (string) $summary['nonempty_rows']],
            ['سطرهای خالی میانی', (string) $summary['empty_rows']],
            ['سطرهای خالی انتهایی', (string) $summary['trailing_empty_rows']],
            ['وارد شده', (string) $summary['imported']],
            ['تکراری در فایل', (string) $summary['duplicate_in_file']],
            ['تکراری در سامانه', (string) $summary['duplicate_in_db']],
            ['قالب نامعتبر', (string) $summary['invalid_format']],
            ['پیشوند ناشناخته', (string) $summary['unknown_prefix']],
            ['محصول غیرسریالی', (string) $summary['product_not_tracked']],
            ['در انتظار', (string) $summary['pending']],
        ];
        if ($batch->last_error) {
            $lines[] = ['آخرین خطا', SerialImportException::publicMessage(new \RuntimeException)];
        }
        foreach ($lines as $line) {
            $this->writeStrings($writer, $line);
        }
    }

    public static function validationLabel(string $status): string
    {
        try {
            return SerialRowValidationStatus::from($status)->label();
        } catch (Throwable) {
            return $status;
        }
    }

    public static function resultLabel(string $status): string
    {
        try {
            return SerialRowResultStatus::from($status)->label();
        } catch (Throwable) {
            return $status;
        }
    }

    public static function reasonLabel(SerialImportRow $row): string
    {
        if ($row->result_status === SerialRowResultStatus::Imported) {
            return 'وارد شد.';
        }

        if ($row->result_status === SerialRowResultStatus::DuplicateInDb) {
            return 'این سریال قبلاً وارد سامانه شده و دوباره ثبت نشد.';
        }

        if ($row->validation_status === SerialRowValidationStatus::DuplicateInFile) {
            return 'تکراری در فایل؛ فقط اولین رخداد بررسی شد.';
        }

        return match ($row->validation_status) {
            SerialRowValidationStatus::Empty => 'سطر خالی.',
            SerialRowValidationStatus::InvalidFormat => 'قالب سریال نامعتبر است.',
            SerialRowValidationStatus::UnknownPrefix => 'پیشوند ناشناخته؛ به هیچ محصولی نگاشت نشد.',
            SerialRowValidationStatus::ProductNotTracked => 'محصول متناظر غیرسریالی است.',
            default => (string) ($row->reason_code ?? ''),
        };
    }
}
