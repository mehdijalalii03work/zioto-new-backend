<?php

namespace Tests\Feature\Inventory;

use App\Jobs\AnalyzeSerialImport;
use App\Jobs\ProcessSerialImport;
use App\Models\ProductSerial;
use App\Models\SerialEvent;
use App\Models\SerialImportRow;
use App\Services\Inventory\SerialImportException;
use App\Services\Inventory\SerialImportReportService;
use App\Services\Inventory\SerialImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Modules\Order\Database\Factories\OrderFactory;
use Modules\Order\Models\OrderItem;
use Modules\Product\Models\Product;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * Stage 2 — spreadsheet reader + import service (plan §6).
 *
 * Only column M (عامل اول) is ever read; every other column is inert.
 * Fixtures are generated per test; the unknown-prefix sample is a copy
 * of the real factory file stored under tests/Fixtures.
 */
class SerialImportTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        parent::tearDown();
    }

    private function makeProduct(string $prefix): Product
    {
        return Product::factory()->serial($prefix)->create([
            'name' => 'شمش '.$prefix,
            'slug' => 'serial-'.$prefix.'-'.uniqid(),
        ]);
    }

    /**
     * Build an xlsx with M1 = عامل اول and the given M-column values.
     *
     * @param  list<mixed>  $mValues  row 2..n of column M
     * @param  array<string, list<mixed>>  $otherColumns  e.g. ['A' => [...], 'N' => [...]]
     */
    private function makeXlsx(array $mValues, array $otherColumns = [], string $header = 'عامل اول'): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('1');
        $sheet->setCellValueExplicit('M1', $header, DataType::TYPE_STRING);

        $row = 2;
        foreach ($mValues as $value) {
            if ($value instanceof \Closure) {
                $value($sheet, $row);
            } else {
                $sheet->setCellValueExplicit('M'.$row, (string) $value, DataType::TYPE_STRING);
            }
            $row++;
        }

        foreach ($otherColumns as $column => $values) {
            $otherRow = 1;
            foreach ($values as $value) {
                if ($value instanceof \Closure) {
                    $value($sheet, $otherRow);
                } else {
                    $sheet->setCellValueExplicit($column.$otherRow, (string) $value, DataType::TYPE_STRING);
                }
                $otherRow++;
            }
        }

        $path = tempnam(sys_get_temp_dir(), 'serials').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        $this->tempFiles[] = $path;

        return $path;
    }

    private function importFile(string $path, string $name = 'test.xlsx'): array
    {
        $service = new SerialImportService;
        $batch = $service->createBatch($path, $name);
        $service->analyze($batch->id);

        return [$batch->fresh(), $service->import($batch->id)];
    }

    public function test_all_pending_rows_are_processed_across_mutating_chunks(): void
    {
        config(['serial_inventory.import_chunk_size' => 2]);
        $this->makeProduct('ton');
        [$batch, $summary] = $this->importFile($this->makeXlsx(['ton001', 'ton002', 'ton003']));
        $this->assertSame(3, $summary['imported']);
        $this->assertSame(0, $summary['pending']);
        $this->assertSame(3, SerialEvent::count());
        $this->assertSame(3, (new SerialImportService)->import($batch->id)['imported']);
        $this->assertSame(3, SerialEvent::count());
    }

    public function test_invalid_serials_are_bounded_and_all_diagnoses_deduplicate(): void
    {
        $this->makeProduct('ton');
        $this->makeProduct('ton9');
        $this->makeProduct('tfv')->update(['track_serials' => false]);
        $long = 'ton'.str_repeat('0', 60);
        [$batch, $summary] = $this->importFile($this->makeXlsx(['ton9', $long, $long.'1', $long, 'abc001', 'ABC۰۰۱', 'tfv001', 'TFV001']));
        $this->assertSame(3, $summary['invalid_format']);
        $this->assertSame(3, $summary['duplicate_in_file']);
        $this->assertSame(1, $summary['unknown_prefix']);
        $this->assertSame(1, $summary['product_not_tracked']);
        $row = $batch->rows()->where('row_number', 3)->firstOrFail();
        $this->assertSame($long, $row->raw_value);
        $this->assertLessThanOrEqual(50, strlen($row->normalized_serial));
    }

    public function test_failed_partial_analysis_retries_without_duplicate_staging(): void
    {
        $this->makeProduct('ton');
        $service = new SerialImportService;
        $batch = $service->createBatch($this->makeXlsx(['ton001', 'ton002']), 'retry.xlsx');
        $service->analyze($batch->id);
        $batch->rows()->where('row_number', 3)->delete();
        $batch->update(['status' => 'failed']);
        $this->assertSame(2, $service->analyze($batch->id)['pending']);
        $this->assertSame(2, $batch->rows()->count());
    }

    public function test_multiple_matching_worksheets_require_explicit_selection(): void
    {
        $this->makeProduct('ton');
        $path = $this->makeXlsx(['ton001']);
        $book = (new \PhpOffice\PhpSpreadsheet\Reader\Xlsx)->load($path);
        $sheet = $book->createSheet();
        $sheet->setTitle('second');
        $sheet->setCellValue('M1', 'عامل اول');
        $sheet->setCellValue('M2', 'ton002');
        (new Xlsx($book))->save($path);
        $book->disconnectWorksheets();
        $service = new SerialImportService;
        $batch = $service->createBatch($path, 'sheets.xlsx');
        try {
            $service->analyze($batch->id);
            $this->fail('Expected explicit worksheet selection.');
        } catch (SerialImportException $e) {
            $this->assertSame(0, $batch->rows()->count());
        }
        $batch->update(['worksheet_name' => 'second']);
        $service->analyze($batch->id);
        $this->assertSame('ton002', $batch->rows()->firstOrFail()->normalized_serial);
    }

    public function test_expanded_zip_limit_and_corrupt_files_return_domain_errors(): void
    {
        config(['serial_inventory.max_expanded_bytes' => 100]);
        $service = new SerialImportService;
        $batch = $service->createBatch($this->makeXlsx(['ton001']), 'large.xlsx');
        try {
            $service->analyze($batch->id);
            $this->fail('Expected expanded size rejection.');
        } catch (SerialImportException $e) {
            $this->assertSame('failed', $batch->fresh()->status->value);
        }
        config(['serial_inventory.max_expanded_bytes' => 100 * 1024 * 1024]);
        file_put_contents($batch->file_path, 'broken');
        $this->expectException(SerialImportException::class);
        $service->analyze($batch->id);
    }

    public function test_job_failure_does_not_expose_sql_or_paths(): void
    {
        $batch = (new SerialImportService)->createBatch('missing.xlsx', 'missing.xlsx');
        (new AnalyzeSerialImport($batch->id))->failed(new \RuntimeException('SQLSTATE secret connection database password'));
        $this->assertStringNotContainsString('SQLSTATE', $batch->fresh()->last_error);
        $this->assertStringNotContainsString('secret', $batch->fresh()->last_error);
    }

    public function test_late_analyze_job_preserves_import_queue_staging(): void
    {
        $this->makeProduct('ton');
        $service = new SerialImportService;
        $batch = $service->createBatch($this->makeXlsx(['ton001']), 'late.xlsx');
        $service->analyze($batch->id);
        $id = $batch->rows()->firstOrFail()->id;
        $batch->update(['status' => 'queued']);
        (new AnalyzeSerialImport($batch->id))->handle($service);
        $this->assertSame('queued', $batch->fresh()->status->value);
        $this->assertSame($id, $batch->rows()->firstOrFail()->id);
    }

    public function test_report_redacts_historical_technical_errors(): void
    {
        $batch = (new SerialImportService)->createBatch('missing.xlsx', 'missing.xlsx');
        $batch->update(['last_error' => 'SQLSTATE secret connection /private/server.xlsx']);
        $path = tempnam(sys_get_temp_dir(), 'report-redaction').'.xlsx';
        $this->tempFiles[] = $path;
        app(SerialImportReportService::class)->writeToFile($batch->fresh(), $path);
        $book = (new \PhpOffice\PhpSpreadsheet\Reader\Xlsx)->load($path);
        $this->assertStringNotContainsString('SQLSTATE', json_encode($book->getSheet(0)->toArray()));
        $book->disconnectWorksheets();
    }

    public function test_late_analysis_failure_does_not_overwrite_completed_import(): void
    {
        $this->makeProduct('ton');
        [$batch] = $this->importFile($this->makeXlsx(['ton001']));

        (new AnalyzeSerialImport($batch->id))->failed(new \RuntimeException('SQLSTATE secret'));

        $this->assertSame('completed', $batch->fresh()->status->value);
        $this->assertNull($batch->last_error);
    }

    public function test_requeued_analysis_clears_previous_import_phase_snapshot(): void
    {
        Queue::fake([AnalyzeSerialImport::class]);
        $this->makeProduct('ton');
        $service = new SerialImportService;
        $batch = $service->createBatch($this->makeXlsx(['ton001', 'ton002']), 'retry.xlsx');
        $service->analyze($batch->id);
        $batch->rows()->where('row_number', 3)->delete();
        $batch->update(['status' => 'failed']);

        $service->dispatchAnalyze($batch);
        (new AnalyzeSerialImport($batch->id))->handle($service);

        $this->assertSame('ready', $batch->fresh()->status->value);
        $this->assertSame(2, $batch->rows()->count());
    }

    public function test_late_import_job_does_not_change_queued_analysis(): void
    {
        $service = new SerialImportService;
        $batch = $service->createBatch($this->makeXlsx(['ton001']), 'analysis.xlsx');
        $batch->update(['status' => 'queued']);

        (new ProcessSerialImport($batch->id))->handle($service);

        $this->assertSame('queued', $batch->fresh()->status->value);
        $this->assertNull($batch->last_error);
        $this->assertSame(0, ProductSerial::count());
    }

    public function test_late_import_failure_does_not_change_queued_analysis(): void
    {
        $batch = (new SerialImportService)->createBatch($this->makeXlsx(['ton001']), 'analysis.xlsx');
        $batch->update(['status' => 'queued']);

        (new ProcessSerialImport($batch->id))->failed(new \RuntimeException('SQLSTATE secret'));

        $this->assertSame('queued', $batch->fresh()->status->value);
        $this->assertNull($batch->last_error);
    }

    public function test_oversized_unicode_raw_values_are_retained_within_text_column_limit(): void
    {
        [$batch, $summary] = $this->importFile($this->makeXlsx([str_repeat('😀', 20000)]));

        $raw = $batch->rows()->firstOrFail()->raw_value;
        $this->assertSame(1, $summary['invalid_format']);
        $this->assertLessThanOrEqual(65535, strlen($raw));
        $this->assertTrue(mb_check_encoding($raw, 'UTF-8'));
        $this->assertStringStartsWith('😀', $raw);
    }

    public function test_real_chunk_failure_preserves_committed_serial_and_retries_without_duplicate_events(): void
    {
        config(['serial_inventory.import_chunk_size' => 1]);
        $this->makeProduct('ton');
        $service = new SerialImportService;
        $batch = $service->createBatch($this->makeXlsx(['ton001', 'ton002']), 'partial.xlsx');
        $service->analyze($batch->id);
        $failOnce = true;
        ProductSerial::created(function (ProductSerial $serial) use (&$failOnce): void {
            if ($serial->serial_code === 'ton002' && $failOnce) {
                $failOnce = false;
                throw new \RuntimeException('SQLSTATE private database detail');
            }
        });

        try {
            $service->import($batch->id);
            $this->fail('Expected second chunk failure.');
        } catch (SerialImportException $exception) {
            $this->assertStringNotContainsString('SQLSTATE', $exception->getMessage());
        }
        $this->assertSame(1, ProductSerial::count());
        $this->assertSame(1, SerialEvent::count());
        $this->assertSame('failed', $batch->fresh()->status->value);

        $summary = $service->import($batch->id);

        $this->assertSame(2, $summary['imported']);
        $this->assertSame(0, $summary['pending']);
        $this->assertSame(2, SerialEvent::count());
        $this->assertNull($batch->fresh()->last_error);
    }

    public function test_unknown_sample_file_imports_zero_and_reports_thirty_unknown(): void
    {
        $path = base_path('tests/Fixtures/serial-unknown-sample.xlsx');
        $this->assertFileExists($path);

        $service = new SerialImportService;
        $batch = $service->createBatch($path, 'warehouse-doc.xlsx');
        $analysis = $service->analyze($batch->id);

        $this->assertSame(0, $analysis['eligible_for_import']);
        $this->assertSame(30, $analysis['unknown_prefix']);
        $this->assertSame(30, $analysis['nonempty_rows']);

        $summary = $service->import($batch->id);

        $this->assertSame(0, $summary['imported']);
        $this->assertSame(30, $summary['unknown_prefix']);
        $this->assertSame(0, ProductSerial::count());
        $this->assertSame(30, SerialImportRow::where('import_batch_id', $batch->id)->count());
    }

    public function test_valid_fixture_imports_three_serials_to_right_products(): void
    {
        $ton = $this->makeProduct('ton');
        $tonPlus = $this->makeProduct('ton9');
        $tfv = $this->makeProduct('tfv');

        $path = $this->makeXlsx(
            ['ton70505511', 'ton90505511', 'tfv71234567'],
            ['A' => ['ردیف کمکی', 1, 1, 1], 'N' => ['x', '=SUM(1,2)', 999999, 'y']],
        );

        [$batch, $summary] = $this->importFile($path);

        $this->assertSame(3, $summary['imported']);
        $this->assertSame(3, ProductSerial::count());
        $this->assertSame($ton->id, ProductSerial::where('serial_code', 'ton70505511')->firstOrFail()->product_id);
        $this->assertSame($tonPlus->id, ProductSerial::where('serial_code', 'ton90505511')->firstOrFail()->product_id);
        $this->assertSame($tfv->id, ProductSerial::where('serial_code', 'tfv71234567')->firstOrFail()->product_id);
        $this->assertSame(
            [$ton->id => 1, $tonPlus->id => 1, $tfv->id => 1],
            $summary['per_product'],
        );
    }

    public function test_rerun_imports_zero_and_marks_duplicates_in_db(): void
    {
        $this->makeProduct('ton');

        $path = $this->makeXlsx(['ton70505511', 'ton70505512', 'ton70505513']);

        $service = new SerialImportService;
        $batch = $service->createBatch($path, 'a.xlsx');
        $service->analyze($batch->id);
        $first = $service->import($batch->id);

        $this->assertSame(3, $first['imported']);

        $batch2 = $service->createBatch($path, 'a.xlsx');
        $analysis = $service->analyze($batch2->id);
        $this->assertSame(0, $analysis['eligible_for_import']);
        $this->assertSame(3, $analysis['pending']);
        $second = $service->import($batch2->id);

        $this->assertSame(0, $second['imported']);
        $this->assertSame(3, $second['duplicate_in_db']);
        $this->assertSame(3, ProductSerial::count());
    }

    public function test_duplicate_within_file_imports_once(): void
    {
        config(['serial_inventory.import_chunk_size' => 2]);

        $this->makeProduct('ton');

        $path = $this->makeXlsx(['ton70505511', 'ton70505512', 'ton70505511', 'TON70505512']);

        [$batch, $summary] = $this->importFile($path);

        $this->assertSame(2, $summary['imported']);
        $this->assertSame(2, $summary['duplicate_in_file']);
        $this->assertSame(2, ProductSerial::count());
    }

    public function test_shipped_serial_reimport_stays_duplicate(): void
    {
        $product = $this->makeProduct('ton');
        $order = OrderFactory::new()->create(['platform' => 'main']);
        $item = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_price' => 1000000,
            'quantity' => 1,
            'subtotal' => 1000000,
        ]);
        ProductSerial::create([
            'product_id' => $product->id,
            'serial_code' => 'ton70505511',
            'serial_prefix' => 'ton',
            'status' => 'shipped',
            'current_order_item_id' => $item->id,
            'unit_number' => 1,
        ]);

        $path = $this->makeXlsx(['ton70505511']);

        [$batch, $summary] = $this->importFile($path);

        $this->assertSame(0, $summary['imported']);
        $this->assertSame(1, $summary['duplicate_in_db']);
        $this->assertSame('shipped', ProductSerial::where('serial_code', 'ton70505511')->firstOrFail()->status->value);
    }

    public function test_corrupt_header_fails_without_storing_rows(): void
    {
        $this->makeProduct('ton');

        $path = $this->makeXlsx(['ton70505511'], header: 'ستون اشتباه');

        $service = new SerialImportService;
        $batch = $service->createBatch($path, 'bad.xlsx');

        try {
            $service->analyze($batch->id);
            $this->fail('Expected SerialImportException for corrupt header.');
        } catch (SerialImportException $e) {
            $this->assertSame('failed', $batch->fresh()->status->value);
            $this->assertSame(0, SerialImportRow::where('import_batch_id', $batch->id)->count());
            $this->assertSame(0, ProductSerial::count());
        }
    }

    public function test_formula_and_spaced_values_are_rejected(): void
    {
        $this->makeProduct('ton');

        $path = $this->makeXlsx([
            'ton70505511',
            fn ($sheet, $row) => $sheet->setCellValue('M'.$row, '=1+1'),
            'ton 70505512',
        ]);

        [$batch, $summary] = $this->importFile($path);

        $this->assertSame(1, $summary['imported']);
        $this->assertSame(2, $summary['invalid_format']);
        $this->assertSame(1, ProductSerial::count());
    }

    public function test_changed_mapping_between_analyze_and_import_stops_batch(): void
    {
        $product = $this->makeProduct('ton');

        $path = $this->makeXlsx(['ton70505511']);

        $service = new SerialImportService;
        $batch = $service->createBatch($path, 'a.xlsx');
        $service->analyze($batch->id);

        $product->update(['track_serials' => false]);

        try {
            $service->import($batch->id);
            $this->fail('Expected SerialImportException for changed mapping.');
        } catch (SerialImportException $e) {
            $this->assertSame(0, ProductSerial::count());
            $this->assertSame('failed', $batch->fresh()->status->value);
            $this->assertSame(0, SerialImportRow::where('import_batch_id', $batch->id)
                ->where('result_status', 'imported')->count());
        }
    }

    public function test_resume_after_committed_chunk_skips_processed_rows(): void
    {
        config(['serial_inventory.import_chunk_size' => 1]);

        $product = $this->makeProduct('ton');

        $service = new SerialImportService;
        $path = $this->makeXlsx(['ton70505511', 'ton70505512']);
        $batch = $service->createBatch($path, 'a.xlsx');
        $service->analyze($batch->id);

        // Simulate a committed first chunk: row 1 already imported.
        $firstRow = SerialImportRow::where('import_batch_id', $batch->id)->where('row_number', 2)->firstOrFail();
        $serial = ProductSerial::create([
            'product_id' => $product->id,
            'serial_code' => 'ton70505511',
            'serial_prefix' => 'ton',
            'status' => 'available',
            'import_row_id' => $firstRow->id,
        ]);
        $firstRow->update([
            'product_serial_id' => $serial->id,
            'result_status' => 'imported',
            'processed_at' => now(),
        ]);

        $serialsBefore = ProductSerial::count();

        $summary = $service->import($batch->id);

        // Only the pending row was processed; the summary reports the
        // definitive batch state (both rows imported).
        $this->assertSame(1, ProductSerial::count() - $serialsBefore);
        $this->assertSame(2, $summary['imported']);
        $this->assertSame(0, $summary['pending']);
        $this->assertSame(2, ProductSerial::count());
        $this->assertSame('completed', $batch->fresh()->status->value);
    }

    public function test_summary_counters_add_up(): void
    {
        $this->makeProduct('ton');

        $path = $this->makeXlsx(['ton70505511', 'ton70505511', 'zzzbad', '', 'ton70505512']);

        [$batch, $summary] = $this->importFile($path);

        $this->assertSame(
            $summary['imported'] + $summary['duplicate_in_file'] + $summary['duplicate_in_db']
            + $summary['invalid_format'] + $summary['unknown_prefix'] + $summary['product_not_tracked']
            + $summary['pending'],
            $summary['nonempty_rows'],
        );
    }
}
