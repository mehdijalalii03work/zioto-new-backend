<?php

namespace App\Services;

use App\Models\RahkaranSerial;
use App\Support\InventorySource;
use DomainException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Modules\Product\Models\Product;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Reader\Exception;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use ZipArchive;

class OfflineInventoryImport
{
    public function __construct(private RahkaranInventoryService $inventory) {}

    public function preview(string $path, string $documentNumber): array
    {
        $this->requireOffline();

        return $this->prepare($this->readRows($path), trim($documentNumber));
    }

    public function receive(string $path, string $documentNumber, int $userId, string $previewHash): array
    {
        $rows = $this->readRows($path);
        $documentNumber = trim($documentNumber);

        return Cache::lock(InventorySource::SYNC_LOCK, 300)->get(function () use ($rows, $documentNumber, $userId, $previewHash): array {
            $this->requireOffline();

            return DB::transaction(function () use ($rows, $documentNumber, $userId, $previewHash): array {
                $preview = $this->prepare($rows, $documentNumber, lock: true);
                if ($preview['errors'] !== [] || ! hash_equals($preview['hash'], $previewHash)) {
                    throw new DomainException('نتیجه سند تغییر کرده است. پیش‌نمایش را دوباره بررسی کنید.');
                }

                DB::table('offline_inventory_receipts')->insert([
                    'document_number' => $documentNumber,
                    'fingerprint' => $preview['fingerprint'],
                    'user_id' => $userId,
                    'summary' => json_encode($preview['items'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                foreach ($preview['items'] as $item) {
                    $product = Product::query()->findOrFail($item['product_id']);
                    if ($item['serial_tracked']) {
                        foreach ($item['serials'] as $serial) {
                            RahkaranSerial::query()->create([
                                'product_id' => $product->id,
                                'serial' => $serial,
                                'status' => 'available',
                                'origin' => 'excel',
                            ]);
                        }

                        $this->inventory->refreshOfflineSerialStock($product);
                    } else {
                        $product->rahkaran_physical_stock = (int) $product->rahkaran_physical_stock + $item['quantity'];
                        $product->stock_quantity = $product->sellableRahkaranStock();
                        $product->save();
                    }
                }

                return $preview['items'];
            }, attempts: 3);
        }) ?: throw new DomainException('ثبت یا همگام‌سازی موجودی در حال اجراست. دوباره تلاش کنید.');
    }

    private function requireOffline(): void
    {
        if (! InventorySource::isOffline()) {
            throw new DomainException('ثبت ورود موجودی فقط در حالت آفلاین امکان‌پذیر است.');
        }
    }

    private function readRows(string $path): array
    {
        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::RDONLY) !== true) {
            throw new DomainException('فایل اکسل معتبر نیست.');
        }

        try {
            $uncompressedSize = 0;
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $uncompressedSize += (int) ($zip->statIndex($index)['size'] ?? 0);
                if ($uncompressedSize > 20 * 1024 * 1024) {
                    throw new DomainException('حجم محتوای فایل اکسل بیش از حد مجاز است.');
                }
            }
        } finally {
            $zip->close();
        }

        $reader = new Xlsx;
        $reader->setReadEmptyCells(false);
        try {
            $workbook = $reader->load($path);
        } catch (Exception) {
            throw new DomainException('فایل اکسل خوانده نشد.');
        }

        try {
            if ($workbook->getSheetCount() !== 1) {
                throw new DomainException('فایل باید یک برگه سند انتقال داشته باشد.');
            }

            $sheet = $workbook->getSheet(0);
            if ($sheet->getHighestDataRow() > 5001 || strlen($sheet->getHighestDataColumn()) > 1) {
                throw new DomainException('فایل بیش از حد بزرگ است.');
            }

            $headers = [];
            foreach ($sheet->getRowIterator(1, 1) as $row) {
                foreach ($row->getCellIterator() as $cell) {
                    $header = $this->normalize($cell->getValue());
                    if ($header !== '') {
                        if (isset($headers[$header])) {
                            throw new DomainException('عنوان ستون تکراری در فایل وجود دارد.');
                        }
                        $headers[$header] = $cell->getColumn();
                    }
                }
            }

            $fields = [
                'code' => 'کالا (کد)',
                'serial' => 'عامل اول',
                'quantity' => 'مقدار',
                'unit' => 'واحد سنجش (عنوان)',
                'document' => 'ردیف کمکی',
                'line' => 'ردیف کمکی قلم سند انبار',
                'date' => 'تاریخ',
                'warehouse' => 'انبار (عنوان انبار)',
                'counterparty' => 'انبار طرف مقابل (عنوان انبار)',
            ];
            foreach (['code', 'serial', 'quantity', 'unit'] as $required) {
                if (! isset($headers[$fields[$required]])) {
                    throw new DomainException('ستون «'.$fields[$required].'» در فایل وجود ندارد.');
                }
            }

            $rows = [];
            for ($number = 2; $number <= $sheet->getHighestDataRow(); $number++) {
                $values = [];
                foreach ($fields as $key => $heading) {
                    $column = $headers[$heading] ?? null;
                    $cell = $column === null ? null : $sheet->getCell($column.$number);
                    if ($cell?->getDataType() === DataType::TYPE_FORMULA) {
                        throw new DomainException("ردیف {$number} مقدار فرمولی دارد.");
                    }

                    $value = $cell?->getValue();
                    $values[$key] = $this->normalize(in_array($key, ['code', 'serial'], true) && is_numeric($value) && ! is_string($value)
                        ? $cell->getFormattedValue()
                        : $value);
                }

                if (array_filter($values, fn (string $value): bool => $value !== '') !== []) {
                    $rows[$number] = $values;
                }
            }

            if ($rows === []) {
                throw new DomainException('فایل ردیف موجودی ندارد.');
            }

            return $rows;
        } finally {
            $workbook->disconnectWorksheets();
        }
    }

    private function prepare(array $rows, string $documentNumber, bool $lock = false): array
    {
        if ($documentNumber === '' || mb_strlen($documentNumber) > 100) {
            throw new DomainException('شماره سند معتبر وارد کنید.');
        }

        $normalizedRows = array_values($rows);
        sort($normalizedRows);
        $fingerprint = hash('sha256', json_encode($normalizedRows, JSON_THROW_ON_ERROR));
        $errors = [];
        if (DB::table('offline_inventory_receipts')->where('document_number', $documentNumber)->orWhere('fingerprint', $fingerprint)->exists()) {
            $errors[] = 'این سند یا همین محتوای فایل قبلاً ثبت شده است.';
        }

        $products = Product::query()->whereIn('rahkaran_item_code', array_unique(array_column($rows, 'code')))
            ->orderBy('id')->when($lock, fn ($query) => $query->lockForUpdate())->get()->keyBy('rahkaran_item_code');
        $existingSerials = RahkaranSerial::query()->whereIn('serial', array_filter(array_unique(array_column($rows, 'serial'))))
            ->get(['serial', 'product_id'])->keyBy(fn (RahkaranSerial $serial): string => mb_strtolower($serial->serial));
        $items = [];
        $serialOwners = [];
        $lines = [];
        foreach ($rows as $number => $row) {
            $code = $row['code'];
            $product = $products->get($code);
            if (! $product || $product->base_product_id) {
                $errors[] = "ردیف {$number} · کد «{$code}» به کالای پایه‌ای متصل نشده است.";

                continue;
            }

            $item = $items[$product->id] ?? [
                'product_id' => $product->id,
                'name' => $product->name,
                'code' => $code,
                'serial_tracked' => $product->rahkaran_serial_tracked,
                'quantity' => 0,
                'serials' => [],
                'duplicates' => 0,
            ];

            if ($product->rahkaran_serial_tracked) {
                $serial = $row['serial'];
                if ($serial === '' || mb_strlen($serial) > 150) {
                    $errors[] = "ردیف {$number} · مقدار عامل اول برای کالای سریالی معتبر نیست.";

                    continue;
                }

                $serialKey = mb_strtolower($serial);
                $existingOwner = $existingSerials->get($serialKey)?->product_id ?? $serialOwners[$serialKey] ?? null;
                if ($existingOwner !== null && (int) $existingOwner !== (int) $product->id) {
                    $errors[] = "ردیف {$number} · سریال به کالای دیگری تعلق دارد.";

                    continue;
                }

                if ($existingOwner !== null) {
                    $item['duplicates']++;
                } else {
                    $serialOwners[$serialKey] = $product->id;
                    $item['serials'][] = $serial;
                    $item['quantity']++;
                }
            } else {
                $quantity = filter_var($row['quantity'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 2147483647]]);
                if ($quantity === false || $row['unit'] !== 'عدد') {
                    $errors[] = "ردیف {$number} · کالای غیرسریالی باید مقدار صحیح مثبت با واحد عدد داشته باشد.";

                    continue;
                }

                $line = $row['document'] !== '' && $row['line'] !== ''
                    ? $row['document'].'/'.$row['line']
                    : "row/{$number}";
                $lineValue = [$code, $quantity, $row['unit']];
                if (isset($lines[$line]) && $lines[$line] !== $lineValue) {
                    $errors[] = "ردیف {$number} · مقدار یا کد قلم سند با ردیف دیگر ناسازگار است.";

                    continue;
                }

                if (! isset($lines[$line])) {
                    $item['quantity'] += $quantity;
                    $lines[$line] = $lineValue;
                }

                if ((int) $product->rahkaran_physical_stock + $item['quantity'] > 2147483647) {
                    $errors[] = "ردیف {$number} · مجموع موجودی از حد مجاز بیشتر است.";
                }
            }

            $items[$product->id] = $item;
        }

        $items = array_values($items);

        return [
            'items' => $items,
            'errors' => $errors,
            'fingerprint' => $fingerprint,
            'hash' => hash('sha256', json_encode([$documentNumber, $fingerprint, $items, setting('offline_serial_origin', 'combined')], JSON_THROW_ON_ERROR)),
        ];
    }

    private function normalize(mixed $value): string
    {
        return trim(strtr((string) $value, [
            'ي' => 'ی', 'ك' => 'ک', '‌' => ' ', '۰' => '0', '۱' => '1', '۲' => '2',
            '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]));
    }
}
