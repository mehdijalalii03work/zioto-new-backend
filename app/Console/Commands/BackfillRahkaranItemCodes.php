<?php

namespace App\Console\Commands;

use App\Services\RahkaranService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Product\Models\Product;
use RuntimeException;
use Throwable;

#[Signature('rahkaran:backfill-item-codes {--apply : ثبت کدهای تطبیق‌داده‌شده در دیتابیس}')]
#[Description('پر کردن کد خالی کالا از فهرست فروش راهکاران بر اساس شناسه فروش ثبت‌شده')]
class BackfillRahkaranItemCodes extends Command
{
    public function handle(RahkaranService $service): int
    {
        if (! Schema::hasColumn('products', 'rahkaran_item_code')) {
            $this->error('ابتدا مهاجرت ستون کد کالای راهکاران را اجرا کنید.');

            return self::FAILURE;
        }

        try {
            $catalog = $this->readCatalog($service);
        } catch (Throwable $exception) {
            report($exception);
            $this->error('دریافت فهرست کالاهای راهکاران ناموفق بود.');

            return self::FAILURE;
        }

        $apply = (bool) $this->option('apply');
        $rows = DB::transaction(fn (): array => $this->matchProducts($catalog, $apply), attempts: 3);
        $this->table(['محصول', 'شناسه فروش', 'کد کالا', 'نتیجه'], $rows);
        $this->info($apply ? 'ثبت کدهای یکتا انجام شد.' : 'پیش‌نمایش. برای ثبت، گزینهٔ --apply را اضافه کنید.');

        return self::SUCCESS;
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function readCatalog(RahkaranService $service): array
    {
        $catalog = [];
        $seenPages = [];
        $pageSize = 100;

        for ($page = 0; $page < 100; $page++) {
            $rows = $service->getProductCatalogPage($page, $pageSize);
            $fingerprint = hash('sha256', serialize($rows));
            if (isset($seenPages[$fingerprint])) {
                throw new RuntimeException('صفحه‌بندی فهرست کالاهای راهکاران تکرار شد.');
            }
            $seenPages[$fingerprint] = true;

            foreach ($rows as $row) {
                $id = (int) ($row['id'] ?? 0);
                $number = trim((string) ($row['number'] ?? ''));
                if ($id > 0 && $number !== '' && mb_strlen($number) <= 100) {
                    $catalog[$id]['code:'.$number] = $number;
                }
            }

            if (count($rows) < $pageSize && $catalog !== []) {
                return $catalog;
            }
        }

        throw new RuntimeException('فهرست کالاهای راهکاران کامل دریافت نشد.');
    }

    private function matchProducts(array $catalog, bool $apply): array
    {
        $products = Product::query()->orderBy('id')->when($apply, fn ($query) => $query->lockForUpdate())->get([
            'id', 'name', 'base_product_id', 'rahkaran_part_ref', 'rahkaran_item_code',
        ]);
        $occupied = $products->filter(fn (Product $product): bool => filled($product->rahkaran_item_code))
            ->keyBy(fn (Product $product): string => mb_strtolower($product->rahkaran_item_code));
        $candidates = [];
        $results = [];

        foreach ($products as $product) {
            if ($product->base_product_id || ! $product->rahkaran_part_ref) {
                continue;
            }

            $numbers = array_values($catalog[$product->rahkaran_part_ref] ?? []);
            if (count($numbers) !== 1) {
                $results[] = [$product->name, $product->rahkaran_part_ref, '—', 'تطبیق یکتا در API پیدا نشد'];

                continue;
            }

            $number = $numbers[0];
            if (filled($product->rahkaran_item_code)) {
                if ($product->rahkaran_item_code !== $number) {
                    $results[] = [$product->name, $product->rahkaran_part_ref, $number, 'کد موجود حفظ شد'];
                }

                continue;
            }

            $candidates[mb_strtolower($number)][] = [$product, $number];
        }

        foreach ($candidates as $key => $matches) {
            foreach ($matches as [$product, $number]) {
                if (count($matches) > 1 || $occupied->has($key)) {
                    $results[] = [$product->name, $product->rahkaran_part_ref, $number, 'کد متعارض'];

                    continue;
                }

                if ($apply) {
                    $product->forceFill(['rahkaran_item_code' => $number])->saveQuietly();
                }
                $results[] = [$product->name, $product->rahkaran_part_ref, $number, $apply ? 'ثبت شد' : 'آماده ثبت'];
            }
        }

        return $results;
    }
}
