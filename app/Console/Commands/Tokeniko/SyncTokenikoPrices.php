<?php

namespace App\Console\Commands\Tokeniko;

use App\Services\PriceBoardService;
use App\Services\TokenikoDirectSyncService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('tokeniko:sync-direct')]
#[Description('Fetch prices from Tokeniko shop API, update DB, send changed prices to Tapsi Shop')]
class SyncTokenikoPrices extends Command
{
    public function handle(
        TokenikoDirectSyncService $sync,
        PriceBoardService $priceBoard,
    ): int {
        $this->info('Syncing prices from Tokeniko shop API...');

        $priceBoard->fetchAndStore();

        $result = $sync->sync();

        if ($result['status'] === 'skipped') {
            $this->warn('Previous sync job is still active. Skipping.');

            return self::SUCCESS;
        }

        if ($result['status'] === 'failure') {
            $this->warn('No prices received from Tokeniko API.');

            return self::FAILURE;
        }

        if ($result['emergency_active']) {
            $this->warn('EMERGENCY LOCK ACTIVE — all Tapsi stock sent as 0.');
        }

        $this->info('Updated '.$result['updated'].' products in DB.');

        if ($result['tapsi_sent'] > 0) {
            $outcome = $result['tapsi_success'] ? 'success' : 'failed';
            $this->info("Sent {$result['tapsi_sent']} products to Tapsi Shop ({$outcome}).");
        } elseif (! config('tapsi.enabled')) {
            $this->warn('Tapsi sync disabled — skipped sending to Tapsi.');
        }

        return self::SUCCESS;
    }
}
