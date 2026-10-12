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
        $result = $stockSync->syncAllStock();

        // Stock intake is stopped; the command stays as an explicit marker.
        $this->info($result['message']);

        return self::SUCCESS;
    }
}
