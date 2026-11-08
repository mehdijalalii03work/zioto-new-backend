<?php

namespace Tests\Feature\Commands\Tapsi;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        $this->assertSame('2026-05-22', Setting::getValue('tapsi_duel_backfill_cursor'));
        $this->assertNotNull(Setting::getValue('tapsi_duel_last_error'));
    }
}
