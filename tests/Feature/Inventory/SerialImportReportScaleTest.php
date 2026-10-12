<?php

namespace Tests\Feature\Inventory;

use App\Models\SerialImportBatch;
use App\Services\Inventory\SerialImportReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use OpenSpout\Reader\XLSX\Reader;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Tests\TestCase;

class SerialImportReportScaleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        ini_set('memory_limit', '128M');
        parent::setUp();
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_fifty_thousand_row_report_preserves_three_sheets_order_and_string_data_under_128_mib(): void
    {
        $batch = SerialImportBatch::factory()->create();
        for ($start = 1; $start <= 50000; $start += 500) {
            $rows = [];
            for ($number = $start + 499; $number >= $start; $number--) {
                $rows[] = ['import_batch_id' => $batch->id, 'row_number' => $number, 'raw_value' => $number === 1 ? '=1+1' : 'ton'.$number, 'normalized_serial' => 'ton'.$number, 'validation_status' => 'unknown_prefix', 'result_status' => 'pending'];
            }
            DB::table('serial_import_rows')->insert($rows);
        }
        unset($rows);
        $path = tempnam(sys_get_temp_dir(), 'report-scale');

        $bufferLevel = ob_get_level();
        try {
            ob_start();
            app(SerialImportReportService::class)->download($batch)->sendContent();
            file_put_contents($path, ob_get_clean());
            $reader = new Reader;
            $reader->open($path);
            $titles = [];
            $rowCount = 0;
            $ordered = true;
            foreach ($reader->getSheetIterator() as $sheet) {
                $titles[] = $sheet->getName();
                if ($sheet->getName() !== 'همه ردیف‌ها') {
                    continue;
                }
                foreach ($sheet->getRowIterator() as $row) {
                    $rowCount++;
                    if ($rowCount > 1) {
                        $ordered = $ordered && $row->getCellAtIndex(0)->getValue() === (string) ($rowCount - 1);
                    }
                    if ($rowCount === 2) {
                        $this->assertSame('1', $row->getCellAtIndex(0)->getValue());
                        $this->assertSame('=1+1', $row->getCellAtIndex(1)->getValue());
                    }
                    if ($rowCount === 50001) {
                        $this->assertSame('50000', $row->getCellAtIndex(0)->getValue());
                    }
                }
            }
            $reader->close();
            $zip = new \ZipArchive;
            $zip->open($path);
            $stream = $zip->getStream('xl/worksheets/sheet2.xml');
            $xml = \XMLReader::fromStream($stream);
            $rawCellFound = false;
            $formulaFound = false;
            while ($xml->read()) {
                if ($xml->nodeType !== \XMLReader::ELEMENT) {
                    continue;
                }
                $formulaFound = $formulaFound || $xml->localName === 'f';
                if ($xml->localName === 'c' && $xml->getAttribute('r') === 'B2') {
                    $rawCellFound = true;
                    $this->assertSame('inlineStr', $xml->getAttribute('t'));
                }
            }
            $xml->close();
            fclose($stream);
            $zip->close();
            $this->assertTrue($rawCellFound);
            $this->assertFalse($formulaFound);
            $this->assertSame(['خلاصه', 'همه ردیف‌ها', 'تفکیک محصولات'], $titles);
            $this->assertSame(50001, $rowCount);
            $this->assertTrue($ordered);
            $this->assertLessThanOrEqual(128 * 1024 * 1024, memory_get_peak_usage(true));
        } finally {
            while (ob_get_level() > $bufferLevel) {
                ob_end_clean();
            }
            unlink($path);
        }
    }
}
