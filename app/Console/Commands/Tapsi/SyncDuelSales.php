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
    protected $signature = 'tapsi:sync-duel {--mode=recent} {--max-windows=4}';

    protected $description = 'Synchronize Tapsi Shop sales used by the Duel report';

    public function handle(TapsiShopService $tapsi, TapsiOrderProjector $projector): int
    {
        if (! config('tapsi.enabled') || ! $tapsi->hasToken()) {
            $this->error('Tapsi Shop integration or API token is not configured.');

            return self::FAILURE;
        }
        $lock = Cache::lock('tapsi:sync-duel', 86400);
        if (! $lock->get()) {
            $this->warn('A Tapsi sales sync is already running.');

            return self::SUCCESS;
        }
        try {
            $mode = (string) $this->option('mode');
            if ($mode === 'recent') {
                $this->refreshWebhookOrders($tapsi, $projector);
            }
            $from = Carbon::parse(config('tapsi.report_start_date', '2026-05-22'))->startOfDay();
            if ($mode === 'recent') {
                $from = max($from, now()->subDays(7)->startOfDay());
            } elseif ($mode === 'backfill') {
                $from = Carbon::parse(Setting::getValue('tapsi_duel_backfill_cursor', $from->toDateString()))->startOfDay();
            } elseif ($mode === 'reconcile') {
                $from = Carbon::parse(Setting::getValue('tapsi_duel_reconcile_cursor', $from->toDateString()))->startOfDay();
            } else {
                throw new \InvalidArgumentException('Mode must be recent, reconcile, or backfill.');
            }
            $maxWindows = $mode === 'recent' ? 2 : max(1, (int) $this->option('max-windows'));
            $windows = 0;
            while ($from->lte(now()) && $windows < $maxWindows) {
                $to = min($from->copy()->addDays(6)->endOfDay(), now());
                $this->syncWindow($tapsi, $projector, $from, $to, $mode !== 'recent');
                $windows++;
                if ($mode === 'backfill') {
                    if ($to->isToday()) {
                        $this->saveSetting('tapsi_duel_backfill_complete', '1');
                        $this->saveSetting('tapsi_duel_backfill_cursor', $to->toDateString());
                        break;
                    }
                    $from = $to->copy()->addDay()->startOfDay();
                    $this->saveSetting('tapsi_duel_backfill_cursor', $from->toDateString());
                } elseif ($mode === 'reconcile') {
                    $from = $to->copy()->addSecond();
                    $this->saveSetting('tapsi_duel_reconcile_cursor', $from->gt(now())
                        ? config('tapsi.report_start_date', '2026-05-22') : $from->toDateString());
                } else {
                    $from = $to->copy()->addSecond();
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

    private function syncWindow(TapsiShopService $tapsi, TapsiOrderProjector $projector, Carbon $from, Carbon $to, bool $forceDetails): void
    {
        $page = 0;
        do {
            $response = $tapsi->getOrders($page, 50, [
                'fromDate' => $from->toIso8601String(), 'toDate' => $to->toIso8601String(),
                'orderStatusId' => ['4', '6', '9'],
            ]);
            if (! ($response['success'] ?? false)) {
                throw new \RuntimeException('Tapsi rejected order list request.');
            }
            $orders = $response['data']['items'] ?? [];
            foreach ($orders as $summary) {
                $id = (string) ($summary['id'] ?? '');
                if ($id === '' || ! in_array((string) ($summary['stateCode'] ?? ''), ['4', '6', '9'], true)) {
                    continue;
                }
                $existing = Order::withoutTenantScope()->where('tapsi_order_id', $id)->first();
                if (! $forceDetails && $existing && $existing->tapsi_synced_at && $existing->tapsi_order_status_id === (string) $summary['stateCode']
                    && (int) $existing->total_amount === (int) $summary['finalPrice']) {
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
            $page++;
            if ($page * 50 >= (int) ($response['data']['totalItems'] ?? 0)) {
                break;
            }
        } while (true);
    }

    private function refreshWebhookOrders(TapsiShopService $tapsi, TapsiOrderProjector $projector): void
    {
        $orders = Order::withoutTenantScope()->where('platform', 'tapsi')
            ->whereNull('tapsi_synced_at')->whereNotNull('tapsi_order_id')
            ->orderBy('id')->limit(10)->get();
        foreach ($orders as $order) {
            $response = $tapsi->getOrders(0, 20, [
                'orderId' => (string) $order->tapsi_order_id,
                'fromDate' => $order->created_at->copy()->startOfDay()->toIso8601String(),
                'toDate' => $order->created_at->copy()->addDays(6)->endOfDay()->toIso8601String(),
                'orderStatusId' => ['4', '6', '9'],
            ]);
            if (! ($response['success'] ?? false)) {
                throw new \RuntimeException('Tapsi rejected webhook reconciliation request.');
            }
            foreach ($response['data']['items'] ?? [] as $summary) {
                if ((string) ($summary['id'] ?? '') !== (string) $order->tapsi_order_id) {
                    continue;
                }
                $details = $tapsi->getOrderDetails((string) $order->tapsi_order_id);
                if (! ($details['success'] ?? false)) {
                    throw new \RuntimeException('Tapsi rejected webhook order details request.');
                }
                $projector->apply($summary, $details);
                break;
            }
        }
    }

    private function saveSetting(string $key, string $value): void
    {
        Setting::updateOrCreate(['key' => $key], [
            'value' => $value, 'type' => 'string', 'category' => 'tapsi', 'label' => $key,
        ]);
    }
}
