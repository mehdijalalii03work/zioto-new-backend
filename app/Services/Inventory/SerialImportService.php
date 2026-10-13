<?php

namespace App\Services\Inventory;

use App\Enums\Inventory\SerialEventType;
use App\Enums\Inventory\SerialImportBatchStatus;
use App\Enums\Inventory\SerialRowResultStatus;
use App\Enums\Inventory\SerialRowValidationStatus;
use App\Enums\Inventory\SerialStatus;
use App\Jobs\AnalyzeSerialImport;
use App\Jobs\ProcessSerialImport;
use App\Models\ProductSerial;
use App\Models\SerialEvent;
use App\Models\SerialImportBatch;
use App\Models\SerialImportRow;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Modules\Product\Models\Product;
use Throwable;

/**
 * Staged serial import: analyze (dry-run, read-only on stock) then import.
 *
 * Only the serial column is ever read. Rows are staged in
 * serial_import_rows during analyze; import consumes staged rows and never
 * re-reads the file. Every import chunk runs in its own transaction while
 * already-committed chunks stay untouched on retry.
 */
final class SerialImportService
{
    public function createBatch(string $path, string $originalFilename, ?int $userId = null): SerialImportBatch
    {
        return SerialImportBatch::create([
            'original_filename' => $originalFilename,
            'file_path' => $path,
            'file_hash' => is_file($path) ? hash_file('sha256', $path) : hash('sha256', $path),
            'column_letter' => config('serial_inventory.column_letter', 'M'),
            'header_row' => config('serial_inventory.header_row', 1),
            'parser_version' => config('serial_inventory.parser_version', 'v1'),
            'created_by' => $userId,
            'status' => SerialImportBatchStatus::Analyzing->value,
        ]);
    }

    public function dispatchAnalyze(SerialImportBatch $batch): void
    {
        if ($this->runsSynchronously()) {
            $this->analyze($batch->id);

            return;
        }
        DB::transaction(function () use ($batch): void {
            $locked = SerialImportBatch::lockForUpdate()->findOrFail($batch->id);
            if (! in_array($locked->status, [SerialImportBatchStatus::Analyzing, SerialImportBatchStatus::Failed], true) || $locked->rows()->whereIn('result_status', ['imported', 'duplicate_in_db'])->exists()) {
                return;
            }
            $locked->update(['status' => SerialImportBatchStatus::Queued->value, 'mapping_snapshot' => null]);
            AnalyzeSerialImport::dispatch($locked->id)->onQueue((string) config('serial_inventory.queue', 'serial-imports'))->afterCommit();
        });
    }

    public function dispatchImport(SerialImportBatch $batch): void
    {
        if ($this->runsSynchronously()) {
            $this->import($batch->id);

            return;
        }
        DB::transaction(function () use ($batch): void {
            $locked = SerialImportBatch::lockForUpdate()->findOrFail($batch->id);
            if (! in_array($locked->status, [SerialImportBatchStatus::Ready, SerialImportBatchStatus::Failed], true) || empty($locked->mapping_snapshot['mapping_hash'])) {
                return;
            }
            $locked->update(['status' => SerialImportBatchStatus::Queued->value, 'last_error' => null, 'completed_at' => null]);
            ProcessSerialImport::dispatch($locked->id)->onQueue((string) config('serial_inventory.queue', 'serial-imports'))->afterCommit();
        });
    }

    private function runsSynchronously(): bool
    {
        return (bool) config('serial_inventory.sync', false);
    }

    /**
     * @return array<string, mixed>
     */
    public function analyze(int $batchId): array
    {
        try {
            return DB::transaction(function () use ($batchId): array {
                $batch = SerialImportBatch::lockForUpdate()->findOrFail($batchId);
                if ($batch->status === SerialImportBatchStatus::Queued && ! empty($batch->mapping_snapshot['mapping_hash'])) {
                    return $this->summarize($batch);
                }
                if (in_array($batch->status, [SerialImportBatchStatus::Ready, SerialImportBatchStatus::Completed, SerialImportBatchStatus::CompletedWithErrors], true)) {
                    return $this->summarize($batch);
                }
                if ($batch->rows()->whereIn('result_status', ['imported', 'duplicate_in_db'])->exists() || $batch->status === SerialImportBatchStatus::Processing) {
                    throw SerialImportException::fileError('ردیف‌های پردازش‌شده را نمی‌توان دوباره تحلیل کرد.');
                }
                $batch->rows()->delete();
                $batch->update(['status' => SerialImportBatchStatus::Analyzing->value, 'started_at' => now(), 'last_error' => null, 'completed_at' => null]);
                $this->runAnalysis($batch);

                return $this->summarize($batch->fresh());
            });
        } catch (Throwable $e) {
            report($e);
            SerialImportBatch::whereKey($batchId)->update(['status' => SerialImportBatchStatus::Failed->value, 'last_error' => SerialImportException::publicMessage($e), 'completed_at' => now()]);
            throw $e instanceof SerialImportException ? $e : SerialImportException::fileError(SerialImportException::publicMessage($e));
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function import(int $batchId): array
    {
        $batch = SerialImportBatch::findOrFail($batchId);

        if ($batch->status === SerialImportBatchStatus::Queued && empty($batch->mapping_snapshot['mapping_hash'])) {
            return $this->summarize($batch);
        }

        if (in_array($batch->status, [SerialImportBatchStatus::Completed, SerialImportBatchStatus::CompletedWithErrors], true)) {
            return $this->summarize($batch);
        }

        if (! in_array($batch->status, [SerialImportBatchStatus::Ready, SerialImportBatchStatus::Processing, SerialImportBatchStatus::Queued, SerialImportBatchStatus::Failed], true)) {
            throw SerialImportException::fileError('این بچ آماده ایمپورت نیست؛ ابتدا تحلیل را اجرا کنید.');
        }

        $batch->update(['status' => SerialImportBatchStatus::Processing->value]);

        try {
            $this->assertMappingUnchanged($batch->fresh());

            $chunkSize = max(1, (int) config('serial_inventory.import_chunk_size', 500));

            SerialImportRow::where('import_batch_id', $batch->id)
                ->where('result_status', 'pending')
                ->chunkById($chunkSize, fn ($rows) => $this->importChunk($batch->fresh(), $rows->all()));
        } catch (Throwable $e) {
            // Any marking done inside the rolled-back chunk is lost, so the
            // failure is recorded here on a fresh read of the batch.
            $batch->refresh();

            if (! in_array($batch->status, [SerialImportBatchStatus::Completed, SerialImportBatchStatus::CompletedWithErrors], true)) {
                $batch->update([
                    'status' => SerialImportBatchStatus::Failed->value,
                    'last_error' => SerialImportException::publicMessage($e),
                    'completed_at' => now(),
                ]);
            }

            report($e);
            throw $e instanceof SerialImportException ? $e : SerialImportException::fileError(SerialImportException::publicMessage($e));
        }

        if ($batch->rows()->where('result_status', 'pending')->exists()) {
            throw SerialImportException::fileError('هنوز ردیف‌هایی در انتظار پردازش هستند.');
        }

        $batch->update([
            'status' => $this->hasRejectedRows($batch) ? SerialImportBatchStatus::CompletedWithErrors->value : SerialImportBatchStatus::Completed->value,
            'last_error' => null,
            'completed_at' => now(),
        ]);

        return $this->summarize($batch->fresh());
    }

    /**
     * @return array<string, mixed>
     */
    public function summarize(SerialImportBatch $batch): array
    {
        $query = DB::table('serial_import_rows')->where('import_batch_id', $batch->id);
        $validationCounts = [];
        $resultCounts = [];
        $validPending = 0;
        $nonempty = 0;

        foreach ((clone $query)->select('validation_status', 'result_status')->selectRaw('COUNT(*) as row_count')->groupBy('validation_status', 'result_status')->get() as $group) {
            $count = (int) $group->row_count;
            $validationCounts[$group->validation_status] = ($validationCounts[$group->validation_status] ?? 0) + $count;
            $resultCounts[$group->result_status] = ($resultCounts[$group->result_status] ?? 0) + $count;
            if ($group->validation_status !== 'empty') {
                $nonempty += $count;
            }
            if ($group->validation_status === 'valid' && $group->result_status === 'pending') {
                $validPending += $count;
            }
        }

        $anticipatedDuplicates = DB::table('serial_import_rows as rows')
            ->join('product_serials as serials', 'serials.serial_code', '=', 'rows.normalized_serial')
            ->where('rows.import_batch_id', $batch->id)
            ->where('rows.validation_status', 'valid')
            ->where('rows.result_status', 'pending')
            ->distinct()->count('serials.serial_code');
        $perProduct = [];
        foreach ((clone $query)->where('result_status', 'imported')->select('product_id')->selectRaw('COUNT(*) as row_count')->groupBy('product_id')->orderBy('product_id')->get() as $group) {
            $perProduct[(int) $group->product_id] = (int) $group->row_count;
        }

        return [
            'batch_id' => $batch->id,
            'status' => $batch->status->value,
            'nonempty_rows' => $nonempty,
            'empty_rows' => $validationCounts['empty'] ?? 0,
            'trailing_empty_rows' => (int) ($batch->trailing_empty_rows ?? 0),
            'imported' => $resultCounts['imported'] ?? 0,
            'eligible_for_import' => $validPending - $anticipatedDuplicates,
            'duplicate_in_file' => $validationCounts['duplicate_in_file'] ?? 0,
            'duplicate_in_db' => $resultCounts['duplicate_in_db'] ?? 0,
            'invalid_format' => $validationCounts['invalid_format'] ?? 0,
            'unknown_prefix' => $validationCounts['unknown_prefix'] ?? 0,
            'product_not_tracked' => $validationCounts['product_not_tracked'] ?? 0,
            'pending' => $resultCounts['pending'] ?? 0,
            'per_product' => $perProduct,
        ];
    }

    /**
     * @return array<int, array{product_id: int, prefix: string, track_serials: bool}>
     */
    public static function mappingList(): array
    {
        return Product::whereNotNull('serial_prefix')
            ->orderBy('id')
            ->get(['id', 'serial_prefix', 'track_serials'])
            ->map(fn (Product $product): array => [
                'product_id' => $product->id,
                'prefix' => (string) $product->serial_prefix,
                'track_serials' => (bool) $product->track_serials,
            ])
            ->values()
            ->all();
    }

    public static function mappingHash(array $mapping): string
    {
        $canonical = $mapping;
        usort($canonical, fn (array $a, array $b): int => $a['product_id'] <=> $b['product_id']);

        return hash('sha256', json_encode($canonical, JSON_UNESCAPED_UNICODE));
    }

    private function runAnalysis(SerialImportBatch $batch): void
    {
        $path = (string) $batch->file_path;

        if (! is_file($path) || strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'xlsx') {
            throw SerialImportException::fileError('فایل معتبر XLSX یافت نشد.');
        }

        if (filesize($path) > (int) config('serial_inventory.max_upload_mb', 10) * 1024 * 1024) {
            throw SerialImportException::fileError('حجم فایل از سقف مجاز بیشتر است.');
        }

        $reader = new SerialSpreadsheetReader($path);
        $column = (string) config('serial_inventory.column_letter', 'M');
        $headerRow = (int) config('serial_inventory.header_row', 1);
        $firstDataRow = (int) config('serial_inventory.first_data_row', 2);

        $sheet = $this->resolveSheet($reader, $column, $headerRow, $batch->worksheet_name);
        $totalRows = $reader->sheetInfo()[$sheet]['totalRows'] ?? 0;

        $maxRows = (int) config('serial_inventory.max_import_rows', 50000);
        if ($totalRows - $firstDataRow + 1 > $maxRows) {
            throw SerialImportException::fileError('تعداد ردیف‌ها از سقف مجاز بیشتر است.');
        }

        // Chunked single-column scan; values are short strings so holding
        // them to find the last non-empty row is cheap.
        $chunkSize = max(1, (int) config('serial_inventory.import_chunk_size', 500));
        $values = [];

        for ($start = $firstDataRow; $start <= $totalRows; $start += $chunkSize) {
            $values += $reader->readColumnRange($sheet, $column, $start, min($start + $chunkSize - 1, $totalRows));
        }

        $lastDataRow = $firstDataRow - 1;
        foreach ($values as $rowNumber => $raw) {
            if ($raw !== null && trim($raw) !== '') {
                $lastDataRow = $rowNumber;
            }
        }

        $mapping = self::mappingList();

        $batch->update([
            'worksheet_name' => $sheet,
            'mapping_snapshot' => [
                'parser_version' => config('serial_inventory.parser_version', 'v1'),
                'mapping_hash' => self::mappingHash($mapping),
                'mapping' => $mapping,
            ],
            'scanned_rows' => max(0, $totalRows - $firstDataRow + 1),
            'trailing_empty_rows' => max(0, $totalRows - max($lastDataRow, $firstDataRow - 1)),
        ]);

        // Persist every row up to the last non-empty one; mid-range empties
        // are stored as empty, trailing rows are only counted.
        $seen = [];
        $records = [];

        for ($rowNumber = $firstDataRow; $rowNumber <= $lastDataRow; $rowNumber++) {
            $raw = $values[$rowNumber] ?? null;
            [$validation, $normalized, $prefix, $productId] = $this->validateRaw($raw, $mapping);

            $canonical = SerialPrefixResolver::normalize((string) $raw);
            if ($validation !== 'empty' && isset($seen[$canonical])) {
                $validation = 'duplicate_in_file';
                $productId = null;
            } elseif ($validation !== 'empty') {
                $seen[$canonical] = true;
            }

            $records[] = [
                'import_batch_id' => $batch->id,
                'row_number' => $rowNumber,
                'raw_value' => is_string($raw) ? mb_strcut($raw, 0, 65535, 'UTF-8') : null,
                'normalized_serial' => $normalized === null ? null : mb_substr($normalized, 0, 50),
                'resolved_prefix' => $prefix,
                'product_id' => $productId,
                'validation_status' => $validation,
                'result_status' => $validation === 'empty' ? 'empty' : 'pending',
                'reason_code' => $validation === 'empty' ? 'empty' : null,
                'processed_at' => $validation === 'empty' ? now() : null,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if (count($records) >= $chunkSize) {
                DB::table('serial_import_rows')->insert($records);
                $records = [];
            }
        }

        if ($records !== []) {
            DB::table('serial_import_rows')->insert($records);
        }

        $batch->update(['status' => SerialImportBatchStatus::Ready->value]);
    }

    /**
     * @return array{0: string, 1: ?string, 2: ?string, 3: ?int}
     */
    private function validateRaw(mixed $raw, array $mapping): array
    {
        if ($raw === null || trim((string) $raw) === '') {
            return ['empty', null, null, null];
        }

        $resolution = SerialPrefixResolver::resolve((string) $raw, $mapping);

        return match ($resolution->status) {
            'valid' => ['valid', $resolution->normalized, $resolution->prefix, $resolution->productId],
            'empty' => ['empty', null, null, null],
            'product_not_tracked' => ['product_not_tracked', $resolution->normalized, $resolution->prefix, $resolution->productId],
            'unknown_prefix' => ['unknown_prefix', $resolution->normalized, null, null],
            default => ['invalid_format', $resolution->normalized !== '' ? $resolution->normalized : mb_substr(trim((string) $raw), 0, 50), null, null],
        };
    }

    public function matchingWorksheets(string $path): array
    {
        $reader = new SerialSpreadsheetReader($path);
        $expected = trim((string) config('serial_inventory.expected_header', 'عامل اول'));

        return array_values(array_filter($reader->sheetNames(), fn (string $sheet): bool => trim((string) $reader->headerValue($sheet, 'M', 1)) === $expected));
    }

    private function resolveSheet(SerialSpreadsheetReader $reader, string $column, int $headerRow, ?string $selected): string
    {
        $expected = trim((string) config('serial_inventory.expected_header', 'عامل اول'));
        $matches = array_values(array_filter($reader->sheetNames(), fn (string $sheet): bool => trim((string) $reader->headerValue($sheet, $column, $headerRow)) === $expected));
        if ($selected !== null && in_array($selected, $matches, true)) {
            return $selected;
        }
        if ($selected === null && count($matches) === 1) {
            return $matches[0];
        }
        throw SerialImportException::fileError(count($matches) > 1 ? 'چند کاربرگ معتبر یافت شد؛ کاربرگ را انتخاب کنید.' : 'عنوان ستون سریال (عامل اول) در فایل یافت نشد.');
    }

    private function assertMappingUnchanged(SerialImportBatch $batch, ?array $mapping = null): void
    {
        $snapshot = $batch->mapping_snapshot ?? [];

        if (($snapshot['mapping_hash'] ?? null) !== self::mappingHash($mapping ?? self::mappingList())) {
            $batch->update([
                'status' => SerialImportBatchStatus::Failed->value,
                'last_error' => SerialImportException::mappingChanged()->getMessage(),
                'completed_at' => now(),
            ]);

            throw SerialImportException::mappingChanged();
        }
    }

    /**
     * @param  list<SerialImportRow>  $stagedRows
     */
    private function importChunk(SerialImportBatch $batch, array $stagedRows): void
    {
        DB::transaction(function () use ($batch, $stagedRows) {
            $lockedBatch = SerialImportBatch::lockForUpdate()->findOrFail($batch->id);

            if ($lockedBatch->status !== SerialImportBatchStatus::Processing && $lockedBatch->status !== SerialImportBatchStatus::Queued) {
                throw SerialImportException::fileError('پردازش بچ متوقف شده است.');
            }

            $mapping = Product::orderBy('id')->lockForUpdate()->get(['id', 'serial_prefix', 'track_serials'])
                ->filter(fn (Product $product): bool => $product->serial_prefix !== null)
                ->map(fn (Product $product): array => ['product_id' => $product->id, 'prefix' => (string) $product->serial_prefix, 'track_serials' => (bool) $product->track_serials])->values()->all();
            $this->assertMappingUnchanged($lockedBatch, $mapping);

            $ids = array_map(fn (SerialImportRow $row): int => $row->id, $stagedRows);
            $rows = SerialImportRow::whereIn('id', $ids)->orderBy('row_number')->lockForUpdate()->get();

            $productIds = $rows->whereNotNull('product_id')->pluck('product_id')->unique()->values()->all();

            if ($productIds !== []) {
                Product::whereIn('id', $productIds)->orderBy('id')->lockForUpdate()->get();
            }

            $normalized = $rows
                ->where('validation_status', 'valid')
                ->map(fn (SerialImportRow $row): ?string => $row->normalized_serial)
                ->filter()
                ->unique()
                ->values()
                ->all();

            $existing = [];
            foreach (array_chunk($normalized, 500) as $chunk) {
                foreach (ProductSerial::whereIn('serial_code', $chunk)->pluck('serial_code')->all() as $code) {
                    $existing[$code] = true;
                }
            }

            foreach ($rows as $row) {
                $this->importRow($row, $existing);
            }
        });
    }

    /**
     * @param  array<string, true>  $existing
     */
    private function importRow(SerialImportRow $row, array &$existing): void
    {
        if ($row->result_status !== SerialRowResultStatus::Pending) {
            return;
        }

        if ($row->validation_status !== SerialRowValidationStatus::Valid || $row->normalized_serial === null || $row->product_id === null) {
            $row->update([
                'result_status' => SerialRowResultStatus::Rejected->value,
                'reason_code' => $row->validation_status->value,
                'processed_at' => now(),
            ]);

            return;
        }

        if (isset($existing[$row->normalized_serial])) {
            $row->update([
                'result_status' => 'duplicate_in_db',
                'reason_code' => 'duplicate_in_db',
                'processed_at' => now(),
            ]);

            return;
        }

        try {
            $serial = ProductSerial::create([
                'product_id' => $row->product_id,
                'serial_code' => $row->normalized_serial,
                'serial_prefix' => (string) $row->resolved_prefix,
                'import_row_id' => $row->id,
                'status' => SerialStatus::Available->value,
            ]);

            SerialEvent::create([
                'product_serial_id' => $serial->id,
                'event_type' => SerialEventType::Imported->value,
                'from_status' => null,
                'to_status' => SerialStatus::Available->value,
                'operation_key' => "import:{$row->import_batch_id}:{$row->id}",
                'created_at' => now(),
            ]);
        } catch (QueryException $e) {
            // A concurrent import won the race; confirm the rival exists
            // before calling it a duplicate (never swallow real FK errors).
            if (! $this->isUniqueViolation($e) || ProductSerial::where('serial_code', $row->normalized_serial)->doesntExist()) {
                report($e);
                throw $e instanceof SerialImportException ? $e : SerialImportException::fileError(SerialImportException::publicMessage($e));
            }

            $row->update([
                'result_status' => 'duplicate_in_db',
                'reason_code' => 'duplicate_in_db',
                'processed_at' => now(),
            ]);

            $existing[$row->normalized_serial] = true;

            return;
        }

        $existing[$row->normalized_serial] = true;

        $row->update([
            'product_serial_id' => $serial->id,
            'result_status' => 'imported',
            'reason_code' => null,
            'processed_at' => now(),
        ]);
    }

    private function isUniqueViolation(QueryException $e): bool
    {
        return in_array($e->errorInfo[0] ?? null, ['23000', '23505'], true);
    }

    private function hasRejectedRows(SerialImportBatch $batch): bool
    {
        return SerialImportRow::where('import_batch_id', $batch->id)
            ->whereIn('result_status', [SerialRowResultStatus::Rejected->value, SerialRowResultStatus::DuplicateInDb->value])
            ->exists();
    }
}
