<?php

namespace Tests\Feature\Admin;

use App\Enums\Permission;
use App\Enums\Role;
use App\Filament\Pages\SerialImport;
use App\Jobs\AnalyzeSerialImport;
use App\Jobs\ProcessSerialImport;
use App\Models\SerialImportBatch;
use App\Models\SerialImportRow;
use App\Models\User;
use App\Services\Inventory\SerialImportService;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\File;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Modules\Product\Models\Product;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx as XlsxReader;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class SerialImportTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private array $tempFiles = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        Filament::setCurrentPanel('admin');
    }

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        parent::tearDown();
    }

    private function staffUser(Role $role): User
    {
        return User::factory()->create()->assignRole($role->value);
    }

    private function makeProduct(string $prefix): Product
    {
        return Product::factory()->serial($prefix)->create([
            'name' => 'شمش '.$prefix,
            'slug' => 'serial-'.$prefix.'-'.uniqid(),
        ]);
    }

    private function makeXlsx(array $mValues): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('1');
        $sheet->setCellValueExplicit('M1', 'عامل اول', DataType::TYPE_STRING);

        $row = 2;
        foreach ($mValues as $value) {
            $sheet->setCellValueExplicit('M'.$row, (string) $value, DataType::TYPE_STRING);
            $row++;
        }

        $path = tempnam(sys_get_temp_dir(), 'serials').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        $this->tempFiles[] = $path;

        return $path;
    }

    private function importBatchUrl(SerialImportBatch $batch): string
    {
        return URL::route('filament.admin.pages.serial-import', ['batch' => $batch->id]);
    }

    public function test_manager_with_import_permission_can_view_import_page(): void
    {
        $manager = $this->staffUser(Role::Manager);

        $this->actingAs($manager, 'web')
            ->get(URL::route('filament.admin.pages.serial-import'))
            ->assertOk();
    }

    public function test_analyst_with_view_only_cannot_view_import_page(): void
    {
        $analyst = $this->staffUser(Role::Analyst);

        $this->actingAs($analyst, 'web')
            ->get(URL::route('filament.admin.pages.serial-import'))
            ->assertForbidden();
    }

    public function test_operator_cannot_view_import_page(): void
    {
        $operator = $this->staffUser(Role::Operator);

        $this->actingAs($operator, 'web')
            ->get(URL::route('filament.admin.pages.serial-import'))
            ->assertForbidden();
    }

    public function test_guest_is_redirected_from_import_page(): void
    {
        $this->get(URL::route('filament.admin.pages.serial-import'))
            ->assertRedirect();
    }

    public function test_analyst_can_view_batch_history_but_operator_cannot(): void
    {
        $analyst = $this->staffUser(Role::Analyst);
        $operator = $this->staffUser(Role::Operator);

        $this->actingAs($analyst, 'web')
            ->get(URL::route('filament.admin.resources.serial-import-batches.index'))
            ->assertOk();

        $this->actingAs($operator, 'web')
            ->get(URL::route('filament.admin.resources.serial-import-batches.index'))
            ->assertForbidden();
    }

    public function test_guest_is_redirected_from_report_download(): void
    {
        $batch = SerialImportBatch::factory()->create();

        $this->get(URL::route('serial-import.report', ['batch' => $batch->id]))
            ->assertRedirect();
    }

    public function test_view_only_user_cannot_download_report(): void
    {
        $analyst = $this->staffUser(Role::Analyst);
        $batch = SerialImportBatch::factory()->create();

        $this->actingAs($analyst, 'web')
            ->get(URL::route('serial-import.report', ['batch' => $batch->id]))
            ->assertForbidden();
    }

    public function test_authorized_download_returns_three_sheet_report(): void
    {
        $this->makeProduct('ton');

        $service = new SerialImportService;
        $batch = $service->createBatch($this->makeXlsx(['ton70505511', '=ABC123', 'ton70505512']), 'a.xlsx');
        $service->analyze($batch->id);
        $service->import($batch->id);

        $manager = $this->staffUser(Role::Manager);

        $response = $this->actingAs($manager, 'web')
            ->get(URL::route('serial-import.report', ['batch' => $batch->id]));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $download = tempnam(sys_get_temp_dir(), 'report').'.xlsx';
        $this->tempFiles[] = $download;
        file_put_contents($download, $response->streamedContent());

        $workbook = (new XlsxReader)->load($download);
        $titles = [];
        foreach ($workbook->getAllSheets() as $sheet) {
            $titles[] = $sheet->getTitle();
            $this->assertTrue($sheet->getRightToLeft());
        }

        $this->assertSame(['خلاصه', 'همه ردیف‌ها', 'تفکیک محصولات'], $titles);

        // A formula-looking raw value must survive as plain text (no injection).
        $rowsSheet = $workbook->getSheetByName('همه ردیف‌ها');
        $this->assertNotNull($rowsSheet);

        $foundFormulaLike = false;
        foreach ($rowsSheet->getRowIterator(2) as $row) {
            $raw = $rowsSheet->getCell('B'.$row->getRowIndex())->getValue();
            if ($raw instanceof RichText) {
                $raw = $raw->getPlainText();
            }
            if (is_string($raw) && str_starts_with($raw, '=')) {
                $foundFormulaLike = true;
                $this->assertSame('=ABC123', $raw);
                $this->assertSame(DataType::TYPE_INLINE, $rowsSheet->getCell('B'.$row->getRowIndex())->getDataType());
            }
        }

        $this->assertTrue($foundFormulaLike, 'Expected a formula-looking raw value in the report.');

        $workbook->disconnectWorksheets();
        unset($workbook);
    }

    public function test_page_shows_batch_result_after_refresh(): void
    {
        $this->makeProduct('ton');

        $service = new SerialImportService;
        $batch = $service->createBatch($this->makeXlsx(['ton70505511']), 'refresh-check.xlsx');
        $service->analyze($batch->id);
        $service->import($batch->id);

        $manager = $this->staffUser(Role::Manager);

        $this->actingAs($manager, 'web')
            ->get($this->importBatchUrl($batch->fresh()))
            ->assertOk()
            ->assertSee('refresh-check.xlsx', escape: false)
            ->assertSee('تکمیل شده', escape: false);
    }

    public function test_download_bounds_unicode_cell_text_without_changing_retained_raw_rows(): void
    {
        $batch = SerialImportBatch::factory()->create(['status' => 'completed_with_errors']);
        $oversized = '='.str_repeat('آ', 32767);
        $boundary = '='.str_repeat('آ', 32766);
        $first = SerialImportRow::factory()->create(['import_batch_id' => $batch->id, 'row_number' => 2, 'raw_value' => $oversized, 'validation_status' => 'invalid_format', 'result_status' => 'rejected']);
        $second = SerialImportRow::factory()->create(['import_batch_id' => $batch->id, 'row_number' => 3, 'raw_value' => $boundary, 'validation_status' => 'invalid_format', 'result_status' => 'rejected']);

        $response = $this->actingAs($this->staffUser(Role::Manager), 'web')->get(URL::route('serial-import.report', ['batch' => $batch->id]));
        $response->assertOk();
        $path = tempnam(sys_get_temp_dir(), 'report-bounds').'.xlsx';
        $this->tempFiles[] = $path;
        file_put_contents($path, $response->streamedContent());
        $workbook = (new XlsxReader)->load($path);

        $this->assertSame(3, $workbook->getSheetCount());
        $rows = $workbook->getSheetByName('همه ردیف‌ها');
        $this->assertSame($boundary, $rows->getCell('B2')->getValue()->getPlainText());
        $this->assertSame($boundary, $rows->getCell('B3')->getValue()->getPlainText());
        $this->assertSame(DataType::TYPE_INLINE, $rows->getCell('B2')->getDataType());
        $this->assertSame($oversized, $first->fresh()->raw_value);
        $this->assertSame($boundary, $second->fresh()->raw_value);
        $workbook->disconnectWorksheets();
    }

    public function test_livewire_upload_dispatches_jobs_and_polls_persisted_progress(): void
    {
        Queue::fake([AnalyzeSerialImport::class, ProcessSerialImport::class]);
        $this->makeProduct('ton');
        $manager = $this->staffUser(Role::Manager);
        Storage::fake('local');
        $storedPath = Storage::disk('local')->putFileAs('serial-imports', new File($this->makeXlsx(['ton001', 'ton002'])), 'upload.xlsx');
        $component = Livewire::actingAs($manager)->test(SerialImport::class)
            ->call('analyzeStoredFile', $storedPath, 'upload.xlsx')->assertSet('summary.status', 'queued');
        $batch = SerialImportBatch::latest('id')->firstOrFail();
        Queue::assertPushed(AnalyzeSerialImport::class, fn ($job) => $job->batchId === $batch->id);
        (new AnalyzeSerialImport($batch->id))->handle(new SerialImportService);
        $component->call('refreshProgress')->assertSet('summary.eligible_for_import', 2)->call('runImport')->call('runImport');
        Queue::assertPushed(ProcessSerialImport::class, 1);
        (new ProcessSerialImport($batch->id))->handle(new SerialImportService);
        $component->call('refreshProgress')->assertSet('summary.imported', 2);
        Livewire::actingAs($manager)->withQueryParams(['batch' => $batch->id])->test(SerialImport::class)->assertSet('summary.imported', 2);
    }

    public function test_upload_method_rejects_arbitrary_private_paths(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('secret.xlsx', 'secret');
        Livewire::actingAs($this->staffUser(Role::Manager))->test(SerialImport::class)
            ->call('analyzeStoredFile', 'serial-imports/../secret.xlsx', 'secret.xlsx')->assertForbidden();
        $this->assertSame(0, SerialImportBatch::count());
    }

    public function test_mutation_rechecks_permission_after_page_mount(): void
    {
        $manager = $this->staffUser(Role::Manager);
        $component = Livewire::actingAs($manager)->test(SerialImport::class);
        $manager->syncRoles([Role::Analyst->value]);
        $component->call('runImport')->assertForbidden();
    }

    public function test_missing_import_permission_denies_without_crashing(): void
    {
        $user = User::factory()->create();
        \Spatie\Permission\Models\Permission::where('name', Permission::SerialImport->value)->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->actingAs($user);
        $this->assertFalse(SerialImport::canAccess());
    }

    public function test_missing_import_permission_denies_report_download_without_crashing(): void
    {
        $user = $this->staffUser(Role::Manager);
        $batch = SerialImportBatch::factory()->create();
        \Spatie\Permission\Models\Permission::where('name', Permission::SerialImport->value)->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($user, 'web')->get(URL::route('serial-import.report', ['batch' => $batch->id]))->assertForbidden();
    }

    public function test_failed_batch_with_corrupt_workbook_remains_viewable(): void
    {
        $path = $this->makeXlsx(['ton001']);
        file_put_contents($path, 'broken');
        $batch = SerialImportBatch::factory()->create(['file_path' => $path, 'status' => 'failed', 'worksheet_name' => null]);

        $this->actingAs($this->staffUser(Role::Manager), 'web')->get($this->importBatchUrl($batch))->assertOk();
    }

    public function test_worksheet_selection_safely_handles_file_corrupted_after_mount(): void
    {
        $path = $this->makeXlsx(['ton001']);
        $batch = SerialImportBatch::factory()->create(['file_path' => $path, 'status' => 'failed', 'worksheet_name' => null]);
        $component = Livewire::actingAs($this->staffUser(Role::Manager))->withQueryParams(['batch' => $batch->id])->test(SerialImport::class)->set('selectedWorksheet', '1');
        file_put_contents($path, 'corrupt private server detail');

        $component->call('selectWorksheet')->assertSet('summary.status', 'failed');

        $batch->refresh();
        $this->assertSame('failed', $batch->status->value);
        $this->assertNotNull($batch->last_error);
        $this->assertStringNotContainsString($path, $batch->last_error);
        $this->assertSame(0, $batch->rows()->count());
    }

    public function test_client_cannot_replace_locked_batch_identity(): void
    {
        $batch = SerialImportBatch::factory()->create();
        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::actingAs($this->staffUser(Role::Manager))->test(SerialImport::class)->set('batch', $batch->id);
    }

    public function test_upload_symlink_outside_private_import_directory_is_denied(): void
    {
        Storage::fake('local');
        Storage::disk('local')->makeDirectory('serial-imports');
        symlink($this->makeXlsx(['ton001']), Storage::disk('local')->path('serial-imports/link.xlsx'));

        Livewire::actingAs($this->staffUser(Role::Manager))->test(SerialImport::class)->call('analyzeStoredFile', 'serial-imports/link.xlsx', 'link.xlsx')->assertForbidden();

        $this->assertSame(0, SerialImportBatch::count());
    }

    public function test_seeder_grants_serial_permissions(): void
    {
        $manager = $this->staffUser(Role::Manager);
        $analyst = $this->staffUser(Role::Analyst);

        $this->assertTrue($manager->hasPermissionTo(Permission::SerialImport->value));
        $this->assertTrue($manager->hasPermissionTo(Permission::SerialView->value));
        $this->assertTrue($analyst->hasPermissionTo(Permission::SerialView->value));
        $this->assertFalse($analyst->hasPermissionTo(Permission::SerialImport->value));
    }
}
