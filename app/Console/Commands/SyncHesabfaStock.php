<?php

namespace App\Console\Commands;

use App\Services\StockSyncService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('hesabfa:sync-stock')]
#[Description('همگام‌سازی موجودی محصولات از حسابفا')]
class SyncHesabfaStock extends Command
{
    public function handle(StockSyncService $stockSync): int
    {
        $this->info('شروع همگام‌سازی موجودی از حسابفا...');

        $result = $stockSync->syncAllStock();

        if ($result['success']) {
            $this->info("{$result['message']}");
            if (! empty($result['errors'])) {
                foreach ($result['errors'] as $error) {
                    $this->error("  - {$error}");
                }
            }

            return self::SUCCESS;
        }

        $this->error($result['message']);

        return self::FAILURE;
    }
}
