<?php

namespace App\Console\Commands\Tapsi;

use App\Models\Setting;
use App\Services\TapsiOrderProjector;
use App\Services\TapsiShopService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Modules\Order\Models\Order;

class SyncDuelSales extends Command
{
    protected $signature = 'tapsi:sync-duel {--mode=recent} {--max-pages=1}';

    protected $description = 'Synchronize Tapsi Shop sales used by the Duel report';

    public function handle(TapsiShopService $tapsi, TapsiOrderProjector $projector): int
    {
        if (! config('tapsi.enabled') || ! $tapsi->hasToken()) {
            $this->error('Tapsi Shop integration or API token is not configured.');

            return self::FAILURE;
        }
        $lock = Cache::lock('tapsi:sync-duel', 7200);
        if (! $lock->get()) {
            $this->warn('A Tapsi sales sync is already running.');

            return self::SUCCESS;
        }
        try {
            $mode = (string) $this->option('mode');
            if (! in_array($mode, ['recent', 'backfill', 'reconcile'], true)) {
                throw new \InvalidArgumentException('Mode must be recent, reconcile, or backfill.');
            }
            if ($mode === 'backfill' && Setting::getValue('tapsi_duel_backfill_complete') === '1') {
                return self::SUCCESS;
            }
            if ($mode === 'reconcile' && Setting::getValue('tapsi_duel_backfill_complete') !== '1') {
                return self::SUCCESS;
            }
            $from = Carbon::parse(config('tapsi.report_start_date', '2026-05-22'))->startOfDay();
            $recentFrom = max($from, now()->subDays(7)->startOfDay());
            $page = $mode === 'recent' ? 0 : (int) Setting::getValue("tapsi_duel_{$mode}_page", 0);
            $maxPages = $mode === 'recent' ? PHP_INT_MAX : max(1, (int) $this->option('max-pages'));
            for ($processed = 0; $processed < $maxPages; $processed++) {
                $totalPages = $this->syncPage($tapsi, $projector, $page, $mode, $from, $recentFrom);
                $page++;
                if ($page >= $totalPages) {
                    if ($mode === 'backfill') {
                        $this->saveSetting('tapsi_duel_backfill_complete', '1');
                    } elseif ($mode === 'reconcile') {
                        $this->saveSetting('tapsi_duel_reconcile_page', '0');
                    }
                    break;
                }
                if ($mode !== 'recent') {
                    $this->saveSetting("tapsi_duel_{$mode}_page", (string) $page);
                }
            }
            $this->saveSetting('tapsi_duel_last_success_at', now()->toIso8601String());
            $this->saveSetting('tapsi_duel_last_error', '');

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $this->saveSetting('tapsi_duel_last_error', $exception->getMessage());
            report($exception);
            $this->error('Tapsi sales sync failed: '.$exception->getMessage());

            return self::FAILURE;
        } finally {
            $lock->release();
        }
    }

    private function syncPage(TapsiShopService $tapsi, TapsiOrderProjector $projector, int $page, string $mode, Carbon $from, Carbon $recentFrom): int
    {
        $pageSize = 50;
        $response = $tapsi->getOrders($page, $pageSize, [
            'fromDate' => now()->subDays(6)->startOfDay()->toIso8601String(),
            'toDate' => now()->toIso8601String(),
            'orderStatusId' => ['4', '6', '9'],
        ]);
        if (! ($response['success'] ?? false)) {
            $status = $response['status'] ?? 'unknown';
            throw new \RuntimeException("Tapsi order list request failed (HTTP {$status}).");
        }
        if (! isset($response['data']['totalItems']) || ! is_array($response['data']['items'] ?? null)) {
            throw new \RuntimeException('Tapsi returned an invalid order list response.');
        }
        $orders = $response['data']['items'];
        $totalPages = max(1, (int) ceil((int) $response['data']['totalItems'] / $pageSize));
        $this->line(sprintf('Page %d/%d: %d orders', $page + 1, $totalPages, count($orders)));
        foreach ($orders as $summary) {
            $id = (string) ($summary['id'] ?? '');
            if ($id === '' || empty($summary['createdOn'])) {
                throw new \RuntimeException('Tapsi returned an order without ID or creation date.');
            }
            $createdOn = Carbon::parse($summary['createdOn']);
            if ($createdOn->lt($from) || $createdOn->gt(now())) {
                continue;
            }
            if (! in_array((string) ($summary['stateCode'] ?? ''), ['4', '6', '9'], true)) {
                continue;
            }
            $existing = Order::withoutTenantScope()->where('tapsi_order_id', $id)->first();
            if ($mode === 'recent' && $createdOn->lt($recentFrom) && ($existing === null || $existing->tapsi_synced_at)) {
                continue;
            }
            if ($mode !== 'reconcile' && $existing?->tapsi_synced_at
                && $existing->tapsi_order_status_id === (string) $summary['stateCode']
                && (int) $existing->total_amount === (int) ($summary['finalPrice'] ?? -1)) {
                continue;
            }
            $details = $tapsi->getOrderDetails($id);
            if (! ($details['success'] ?? false)) {
                throw new \RuntimeException("Tapsi rejected details request for order {$id}.");
            }
            $projector->apply($summary, $details);
            $delay = (int) config('tapsi.order_detail_delay', 11);
            if ($delay > 0) {
                sleep($delay);
            }
        }

        return $totalPages;
    }

    private function saveSetting(string $key, string $value): void
    {
        Setting::updateOrCreate(['key' => $key], [
            'value' => $value, 'type' => 'string', 'category' => 'tapsi', 'label' => $key,
        ]);
    }
}
