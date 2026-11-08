<?php

namespace Tests\Feature\Commands\Tapsi;

use App\Models\Setting;
use App\Services\TapsiOrderProjector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SyncDuelSalesTest extends TestCase
{
    use RefreshDatabase;

    public function test_backfill_imports_confirmed_order_and_records_completion(): void
    {
        $this->travelTo(now()->setDate(2026, 5, 23)->startOfDay());
        config(['tapsi.enabled' => true, 'tapsi.auth_token' => 'test-token', 'tapsi.order_detail_delay' => 0]);
        Http::preventStrayRequests();
        Http::fake([
            '*/orders' => Http::response(['success' => true, 'data' => ['totalItems' => 1, 'items' => [[
                'id' => '8001', 'orderNumber' => 'TS-8001', 'stateCode' => '4', 'finalPrice' => 900000,
                'createdOn' => '2026-05-22T10:00:00Z',
            ]]]]),
            '*/orders/8001' => Http::response(['success' => true, 'data' => ['items' => [[
                'orderItemId' => '8101', 'sku' => 'missing-sku', 'name' => 'شمش', 'quantity' => 1, 'finalPrice' => 900000,
            ]], 'shipments' => []]]),
        ]);

        $this->artisan('tapsi:sync-duel --mode=backfill')->assertExitCode(0);

        $this->assertDatabaseHas('orders', ['tapsi_order_id' => '8001', 'tapsi_order_status_id' => '4', 'status' => 'confirmed']);
        $this->assertDatabaseHas('order_items', ['tapsi_order_item_id' => '8101', 'product_id' => null]);
        $this->assertSame('1', Setting::getValue('tapsi_duel_backfill_complete'));
    }

    public function test_api_failure_keeps_backfill_cursor_and_records_error(): void
    {
        config(['tapsi.enabled' => true, 'tapsi.auth_token' => 'test-token']);
        Http::preventStrayRequests();
        Http::fake(['*/orders' => Http::response(['success' => false], 503)]);

        $this->artisan('tapsi:sync-duel --mode=backfill')->assertExitCode(1);

        $this->assertSame(0, Setting::getValue('tapsi_duel_backfill_page'));
        $this->assertStringContainsString('HTTP 503', Setting::getValue('tapsi_duel_last_error'));
    }

    public function test_backfill_scans_pages_and_imports_only_orders_after_start_date(): void
    {
        $this->travelTo(now()->setDate(2026, 6, 1)->startOfDay());
        config(['tapsi.enabled' => true, 'tapsi.auth_token' => 'test-token', 'tapsi.order_detail_delay' => 0]);
        Http::preventStrayRequests();
        Http::fake([
            '*/orders' => function ($request) {
                $page = $request->data()['pageNumber'];

                return Http::response(['success' => true, 'data' => [
                    'totalItems' => 51,
                    'items' => $page === 0
                        ? array_fill(0, 50, ['id' => 'old', 'stateCode' => '9', 'finalPrice' => 100, 'createdOn' => '2026-05-01T10:00:00Z'])
                        : [['id' => '9001', 'stateCode' => '9', 'finalPrice' => 100, 'createdOn' => '2026-05-24T10:00:00Z']],
                ]]);
            },
            '*/orders/9001' => Http::response(['success' => true, 'data' => ['items' => [], 'shipments' => []]]),
        ]);

        $this->artisan('tapsi:sync-duel --mode=backfill --max-pages=1')->assertExitCode(0);

        $this->assertSame('1', Setting::getValue('tapsi_duel_backfill_page'));
        $this->assertNull(Setting::getValue('tapsi_duel_backfill_complete'));
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => Carbon::parse($request->data()['fromDate'])
            ->diffInDays(Carbon::parse($request->data()['toDate'])) <= 7);

        $this->artisan('tapsi:sync-duel --mode=backfill --max-pages=1')->assertExitCode(0);

        $this->assertDatabaseHas('orders', ['tapsi_order_id' => '9001']);
        $this->assertSame('1', Setting::getValue('tapsi_duel_backfill_complete'));
    }

    public function test_recent_sync_does_not_import_historical_orders_before_backfill(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 9)->startOfDay());
        config(['tapsi.enabled' => true, 'tapsi.auth_token' => 'test-token', 'tapsi.order_detail_delay' => 0]);
        Http::preventStrayRequests();
        Http::fake([
            '*/orders' => Http::response(['success' => true, 'data' => ['totalItems' => 1, 'items' => [[
                'id' => 'old-order', 'stateCode' => '9', 'finalPrice' => 100,
                'createdOn' => '2026-06-01T10:00:00+03:30',
            ]]]]),
        ]);

        $this->artisan('tapsi:sync-duel --mode=recent')->assertExitCode(0);

        $this->assertDatabaseMissing('orders', ['tapsi_order_id' => 'old-order']);
        Http::assertSentCount(1);
    }

    public function test_backfill_restart_skips_orders_already_synced_on_the_same_page(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 9)->startOfDay());
        config(['tapsi.enabled' => true, 'tapsi.auth_token' => 'test-token', 'tapsi.order_detail_delay' => 0]);
        $summary = ['id' => '9002', 'orderNumber' => 'TS-9002', 'stateCode' => '9',
            'finalPrice' => 100, 'createdOn' => '2026-06-01T10:00:00+03:30'];
        app(TapsiOrderProjector::class)->apply($summary, ['data' => ['items' => [], 'shipments' => []]]);
        Http::preventStrayRequests();
        Http::fake(['*/orders' => Http::response(['success' => true, 'data' => [
            'totalItems' => 1, 'items' => [$summary],
        ]])]);

        $this->artisan('tapsi:sync-duel --mode=backfill')->assertExitCode(0);

        Http::assertSentCount(1);
        $this->assertSame('1', Setting::getValue('tapsi_duel_backfill_complete'));
    }
}
