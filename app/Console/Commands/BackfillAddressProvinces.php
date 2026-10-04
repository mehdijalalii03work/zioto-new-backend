<?php

namespace App\Console\Commands;

use App\Models\UserAddress;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

#[Signature('addresses:backfill-provinces {--dry-run : فقط گزارش بده و چیزی ننویس}')]
#[Description('پر کردن province_id آدرس‌های قدیمی از روی شهرشان (ایمپورت وردپرس)')]
class BackfillAddressProvinces extends Command
{
    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $pending = UserAddress::withoutTenantScope()
            ->whereNull('province_id')
            ->whereNotNull('city_id')
            ->count();

        if ($pending === 0) {
            $this->info('همه آدرس‌ها province_id دارند.');

            return self::SUCCESS;
        }

        $this->info("{$pending} آدرس بدون province_id و دارای city_id پیدا شد.");

        if ($dryRun) {
            $this->line('حالت --dry-run: هیچ تغییری ذخیره نشد.');

            return self::SUCCESS;
        }

        $updated = 0;

        UserAddress::withoutTenantScope()
            ->whereNull('province_id')
            ->whereNotNull('city_id')
            ->chunkById(200, function (Collection $addresses) use (&$updated): void {
                foreach ($addresses as $address) {
                    // Replays the UserAddress saving hook, which looks the city's
                    // province up by key — so `city` is deliberately not eager loaded.
                    $address->save();

                    if ($address->province_id !== null) {
                        $updated++;
                    }
                }
            });

        $this->info("بروزرسانی کامل شد. {$updated} آدرس province_id گرفتند.");

        return self::SUCCESS;
    }
}
