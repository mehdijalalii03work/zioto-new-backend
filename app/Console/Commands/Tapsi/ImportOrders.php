<?php

namespace App\Console\Commands\Tapsi;

use App\Services\TapsiOrderProjector;
use App\Services\TapsiShopService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Modules\Order\Models\Order;

class ImportOrders extends Command
{
    protected $signature = 'tapsi:import-orders
        {--from= : Start date (Y-m-d)}
        {--to= : End date (Y-m-d)}
        {--dry-run : Show stats without persisting}
        {--page-size=20 : Page size for Tapsi API}
        {--limit= : Max number of orders to import}
        {--debug : Show debug output}';

    protected $description = 'Import historical successful orders from Tapsi Shop';

    private int $imported = 0;

    private int $skipped = 0;

    private int $failed = 0;

    private int $limit = 0;

    private bool $dryRun = false;

    private bool $verbose = false;

    public function handle(TapsiShopService $tapsi, TapsiOrderProjector $projector): int
    {
        if (! config('tapsi.enabled')) {
            $this->error('Tapsi Shop sync is not enabled. Set TAPSI_SYNC_ENABLED=true.');

            return Command::FAILURE;
        }

        $lock = Cache::lock('tapsi:import-orders', 3600);

        if (! $lock->get()) {
            $this->error('Another import is already running. Try again later.');

            return Command::FAILURE;
        }

        try {
            $this->dryRun = (bool) $this->option('dry-run');
            $this->verbose = (bool) $this->option('debug');
            $this->limit = (int) ($this->option('limit') ?: PHP_INT_MAX);
            $pageSize = (int) $this->option('page-size');

            $fromDate = $this->option('from')
                ? Carbon::parse($this->option('from'))->startOfDay()->toIso8601String()
                : now()->subYear()->toIso8601String();

            $toDate = $this->option('to')
                ? Carbon::parse($this->option('to'))->endOfDay()->toIso8601String()
                : now()->toIso8601String();

            $this->newLine();
            $this->info('Tapsi Shop Order Import');
            $this->line("  From: {$fromDate}");
            $this->line("  To:   {$toDate}");
            $this->line('  Mode: '.($this->dryRun ? 'DRY RUN' : 'LIVE'));
            $this->newLine();

            $this->importOrders($tapsi, $projector, $fromDate, $toDate, $pageSize);

            $this->newLine();
            $this->info("Done! Imported: {$this->imported}, Skipped: {$this->skipped}, Failed: {$this->failed}");

            return Command::SUCCESS;

        } finally {
            $lock->release();
        }
    }

    private function importOrders(TapsiShopService $tapsi, TapsiOrderProjector $projector, string $fromDate, string $toDate, int $pageSize): void
    {
        if ($this->verbose) {
            $this->line('  Fetching order list from Tapsi API...');
        }

        $found = false;

        foreach ($tapsi->getAllSuccessfulOrders($fromDate, $toDate, $pageSize) as $orderData) {
            $found = true;

            if ($this->imported + $this->skipped + $this->failed >= $this->limit) {
                break;
            }

            $tapsiOrderId = (string) ($orderData['id'] ?? '');

            if (empty($tapsiOrderId)) {
                $this->warn('  Skipping order with empty ID');

                $this->failed++;

                continue;
            }

            try {
                $this->processOrder($tapsi, $projector, $tapsiOrderId, $orderData);
            } catch (\Throwable $e) {
                $this->failed++;
                $this->error("  Failed: {$tapsiOrderId} — {$e->getMessage()}");
                Log::error('[TapsiImport] Failed to process order', [
                    'tapsi_order_id' => $tapsiOrderId,
                    'error' => $e->getMessage(),
                ]);
            }

            // Tapsi API rate-limits order details to 1 request per 10 seconds
            $delay = (int) config('tapsi.order_detail_delay', 11);
            if ($delay > 0) {
                sleep($delay);
            }
        }

        if (! $found) {
            $this->warn('  No orders found from Tapsi API for the given date range.');
            $this->warn('  Check that:');
            $this->warn('    - TAPSI_AUTH_TOKEN is valid in .env');
            $this->warn('    - Date range contains completed orders');
            $this->warn('    - Run with --verbose for more details');
        }
    }

    private function processOrder(TapsiShopService $tapsi, TapsiOrderProjector $projector, string $tapsiOrderId, array $summaryData): void
    {
        $details = $tapsi->getOrderDetails($tapsiOrderId);

        if (! ($details['success'] ?? false)) {
            $this->failed++;
            $this->warn("  Failed to fetch details: {$tapsiOrderId}");

            return;
        }

        $orderInfo = $details['data']['order'] ?? [];
        $items = $details['data']['items'] ?? [];
        $shipments = $details['data']['shipments'] ?? [];

        if ($this->option('debug')) {
            $this->line('  [DEBUG] Summary data keys: '.implode(', ', array_keys($summaryData)));
            $this->line('  [DEBUG] Order info keys: '.implode(', ', array_keys($orderInfo)));
            $this->line('  [DEBUG] Items count: '.count($items));
            if (isset($items[0])) {
                $this->line('  [DEBUG] Item 0 keys: '.implode(', ', array_keys($items[0])));
            }
        }

        if ($this->dryRun) {
            $this->imported++;
            $this->line("  Dry run: {$tapsiOrderId} — ".count($items).' items');

            return;
        }

        $projector->apply($summaryData, $details);

        $this->imported++;
        $this->line("  Imported: {$tapsiOrderId} — ".count($items).' items');
    }
}
