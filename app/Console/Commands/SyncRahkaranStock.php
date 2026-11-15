<?php

namespace App\Console\Commands;

use App\Services\RahkaranInventoryService;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('rahkaran:sync-stock')]
class SyncRahkaranStock extends Command
{
    public function handle(RahkaranInventoryService $inventory): int
    {
        if (setting('inventory_source', 'hesabfa') !== 'rahkaran' && ! setting('rahkaran_serial_tracking_enabled', false)) {
            $this->components->info('راهکاران منبع موجودی سایت نیست؛ همگام‌سازی انجام نشد.');

            return self::SUCCESS;
        }

        $result = $inventory->syncAllSerials();
        if (! $result['success']) {
            $this->components->error($result['message']);
            foreach ($result['errors'] ?? [] as $sku => $error) {
                $this->line("{$sku}: {$error}");
            }

            return self::FAILURE;
        }

        $this->components->info($result['message']);

        return self::SUCCESS;
    }
}
