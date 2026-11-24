<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Filament\Pages\ReceiveInventory;
use App\Models\RahkaranSerial;
use App\Models\Setting;
use App\Models\User;
use App\Services\OfflineInventoryImport;
use App\Services\RahkaranInventoryService;
use Database\Seeders\RolePermissionSeeder;
use DomainException;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Modules\Order\Models\Order;
use Modules\Product\Models\Product;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class OfflineInventoryImportTest extends TestCase
{
    use RefreshDatabase;

    private ?string $file = null;

    protected function tearDown(): void
    {
        if ($this->file !== null) {
            unlink($this->file);
        }

        parent::tearDown();
    }

    public function test_receipt_adds_inventory_and_serials_once(): void
    {
        $this->configureOffline();
        $serialProduct = $this->product('021002', true);
        $plain = $this->product('21040', false);
        $plain->update(['rahkaran_physical_stock' => 10, 'rahkaran_reserved_stock' => 2]);
        RahkaranSerial::create(['product_id' => $serialProduct->id, 'serial' => 'ALREADY', 'status' => 'sold']);
        $file = $this->workbook([
            ['1', '1', '1', '021002', 'عدد', '000123'],
            ['1', '2', '1', '021002', 'عدد', 'ALREADY'],
            ['1', '3', '4', '21040', 'عدد', ''],
            ['1', '3', '4', '21040', 'عدد', ''],
        ]);
        $import = app(OfflineInventoryImport::class);

        $preview = $import->preview($file, 'TRANSFER-01');

        $this->assertSame([], $preview['errors']);
        $this->assertSame([1, 4], array_column($preview['items'], 'quantity'));
        $this->assertSame(10, $plain->refresh()->rahkaran_physical_stock);

        $import->receive($file, 'TRANSFER-01', User::factory()->create()->id, $preview['hash']);

        $this->assertDatabaseHas('rahkaran_serials', ['serial' => '000123', 'origin' => 'excel', 'status' => 'available']);
        $this->assertDatabaseHas('rahkaran_serials', ['serial' => 'ALREADY', 'origin' => 'api', 'status' => 'sold']);
        $this->assertSame(14, $plain->refresh()->rahkaran_physical_stock);
        $this->assertSame(12, $plain->stock_quantity);
        $this->assertDatabaseCount('offline_inventory_receipts', 1);
        $this->assertNotEmpty($import->preview($file, 'TRANSFER-02')['errors']);
        $this->assertNotEmpty($import->preview($file, 'TRANSFER-01')['errors']);
    }

    public function test_invalid_mapping_and_cross_product_serials_block_receipt(): void
    {
        $this->configureOffline();
        $first = $this->product('101', true);
        $second = $this->product('102', true);
        RahkaranSerial::create(['product_id' => $first->id, 'serial' => 'USED', 'status' => 'available']);
        $file = $this->workbook([['1', '1', '1', '102', 'عدد', 'USED'], ['1', '2', '1', '999', 'عدد', 'NEW']]);
        $import = app(OfflineInventoryImport::class);
        $preview = $import->preview($file, 'TRANSFER-01');

        $this->assertCount(2, $preview['errors']);
        try {
            $import->receive($file, 'TRANSFER-01', User::factory()->create()->id, $preview['hash']);
            $this->fail('Invalid rows must prevent receipt.');
        } catch (DomainException) {
            $this->assertDatabaseCount('offline_inventory_receipts', 0);
            $this->assertSame(0, RahkaranSerial::where('product_id', $second->id)->count());
        }
    }

    public function test_offline_origin_filters_both_stock_and_reservation(): void
    {
        $this->configureOffline();
        $product = $this->product('101', true);
        RahkaranSerial::create(['product_id' => $product->id, 'serial' => 'REMOTE', 'status' => 'available']);
        RahkaranSerial::create(['product_id' => $product->id, 'serial' => 'LOCAL', 'status' => 'available', 'origin' => 'excel']);
        $this->assertSame(2, $product->sellableSingles());
        $order = Order::factory()->create();
        $item = $order->items()->create(['product_id' => $product->id, 'product_name' => $product->name, 'product_price' => 100, 'quantity' => 1, 'subtotal' => 100]);

        $this->configureOffline('excel');
        $this->assertSame(1, $product->sellableSingles());
        $inventory = app(RahkaranInventoryService::class);
        $inventory->reserveForOrder($order);
        $this->assertDatabaseHas('rahkaran_serials', ['serial' => 'LOCAL', 'status' => 'reserved', 'order_item_id' => $item->id]);
        $this->assertSame(0, $product->sellableSingles());

        $this->configureOffline('api');
        $this->assertSame(1, $product->sellableSingles());
        $inventory->releaseOrder($order);
        $this->assertDatabaseHas('rahkaran_serials', ['serial' => 'LOCAL', 'status' => 'available', 'order_item_id' => null]);
        $this->assertSame(1, $product->sellableSingles());
    }

    public function test_admin_can_preview_and_confirm_an_uploaded_document(): void
    {
        $this->configureOffline();
        $this->seed(RolePermissionSeeder::class);
        Filament::setCurrentPanel('admin');
        $admin = User::factory()->create()->assignRole(Role::Admin->value);
        $product = $this->product('21040', false);
        $path = $this->workbook([['1', '1', '2', '21040', 'عدد', '']]);
        $upload = UploadedFile::fake()->createWithContent('transfer.xlsx', file_get_contents($path));

        Livewire::actingAs($admin, 'web')->test(ReceiveInventory::class)
            ->set('data.document_number', 'TRANSFER-01')
            ->set('data.workbook', [$upload])
            ->call('preview')
            ->assertHasNoErrors()
            ->assertSee('پیش‌نمایش افزایش موجودی')
            ->assertSee('تأیید و ثبت ورود موجودی')
            ->call('receive')
            ->assertHasNoErrors()
            ->assertNotified('ورود موجودی ثبت شد');

        $this->assertSame(2, $product->refresh()->rahkaran_physical_stock);
    }

    public function test_upload_is_rejected_when_offline_mode_is_disabled(): void
    {
        $this->expectException(DomainException::class);

        app(OfflineInventoryImport::class)->preview('unused.xlsx', 'TRANSFER-01');
    }

    private function configureOffline(string $origin = 'combined'): void
    {
        foreach (['inventory_source' => 'offline', 'offline_serial_origin' => $origin] as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => $value, 'type' => 'string', 'category' => 'inventory', 'label' => $key]);
        }
    }

    private function product(string $code, bool $tracked): Product
    {
        return Product::create([
            'name' => $code, 'slug' => 'product-'.$code, 'sku' => 'sku-'.$code, 'status' => 'published',
            'price' => 100, 'rahkaran_item_code' => $code, 'rahkaran_serial_tracked' => $tracked,
        ]);
    }

    private function workbook(array $rows): string
    {
        $this->file = tempnam(sys_get_temp_dir(), 'receipt-test-');
        $workbook = new Spreadsheet;
        $sheet = $workbook->getActiveSheet();
        $sheet->fromArray(['ردیف کمکی', 'ردیف کمکی قلم سند انبار', 'مقدار', 'کالا (کد)', 'واحد سنجش (عنوان)', 'عامل اول']);
        foreach ($rows as $index => $row) {
            foreach ($row as $column => $value) {
                $sheet->setCellValueExplicit([$column + 1, $index + 2], $value, DataType::TYPE_STRING);
            }
        }
        (new Xlsx($workbook))->save($this->file);
        $workbook->disconnectWorksheets();

        return $this->file;
    }
}
