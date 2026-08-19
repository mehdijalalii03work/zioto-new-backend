<?php

namespace Tests\Feature\Services;

use App\Services\TapsiShopService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TapsiShopServiceTest extends TestCase
{
    use RefreshDatabase;

    private TapsiShopService $service;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'tapsi.base_url' => 'https://vendorgw.tapsi.shop/web/hub/vendors/v1',
            'tapsi.auth_token' => 'test-token',
            'tapsi.chunk_size' => 30,
            'tapsi.delay_between_chunks' => 0,
        ]);

        $this->service = new TapsiShopService;
    }

    public function test_calculate_tapsi_price_applies_markup_and_converts_to_rial(): void
    {
        $belowThreshold = $this->service->calculateTapsiPrice(10_000_000);
        $this->assertSame(102_000_000, $belowThreshold);

        $aboveThreshold = $this->service->calculateTapsiPrice(60_000_000);
        $this->assertSame(606_000_000, $aboveThreshold);

        $atThreshold = $this->service->calculateTapsiPrice(50_000_000);
        $this->assertSame(505_000_000, $atThreshold);
    }

    public function test_send_batch_splits_products_into_chunks(): void
    {
        $requests = [];

        Http::fake(function (Request $request) use (&$requests) {
            $requests[] = $request;

            return Http::response(['success' => true], 200);
        });

        $products = collect(range(1, 40))
            ->map(fn (int $i) => [
                'id' => "SKU-{$i}",
                'price' => 1000,
                'specialprice' => 1000,
                'stock' => 1,
                'referenceCode' => "ref-{$i}",
            ])
            ->all();

        $result = $this->service->sendBatch($products);

        $this->assertTrue($result);
        $this->assertCount(2, $requests);
        $this->assertCount(30, $requests[0]->data()['products']);
        $this->assertCount(10, $requests[1]->data()['products']);
    }

    public function test_send_batch_refreshes_token_once_and_retries_on_401(): void
    {
        $requests = [];
        $isFirstProductsCall = true;

        Http::fake(function (Request $request) use (&$requests, &$isFirstProductsCall) {
            $requests[] = $request;

            if (str_ends_with($request->url(), '/refresh-token')) {
                return Http::response(['success' => true, 'data' => ['token' => 'new-token']], 200);
            }

            if ($isFirstProductsCall) {
                $isFirstProductsCall = false;

                return Http::response(['success' => false], 401);
            }

            return Http::response(['success' => true], 200);
        });

        $result = $this->service->sendBatch([[
            'id' => 'SKU-1',
            'price' => 1000,
            'specialprice' => 1000,
            'stock' => 1,
            'referenceCode' => 'ref-1',
        ]]);

        $this->assertTrue($result);
        $this->assertCount(3, $requests);
        $this->assertSame(['new-token'], $requests[2]->header('TapsiShop.Hub.Authorization'));
    }

    public function test_send_batch_aborts_on_failed_chunk(): void
    {
        $requests = [];

        Http::fake(function (Request $request) use (&$requests) {
            $requests[] = $request;

            return Http::response(['success' => false, 'messages' => [['message' => 'Rejected']]], 400);
        });

        $products = collect(range(1, 40))
            ->map(fn (int $i) => [
                'id' => "SKU-{$i}",
                'price' => 1000,
                'specialprice' => 1000,
                'stock' => 1,
                'referenceCode' => "ref-{$i}",
            ])
            ->all();

        $result = $this->service->sendBatch($products);

        $this->assertFalse($result);
        $this->assertCount(1, $requests);
    }

    public function test_send_batch_returns_false_when_token_missing(): void
    {
        config(['tapsi.auth_token' => '']);

        Http::fake();

        $result = $this->service->sendBatch([[
            'id' => 'SKU-1',
            'price' => 1000,
            'specialprice' => 1000,
            'stock' => 1,
            'referenceCode' => 'ref-1',
        ]]);

        $this->assertFalse($result);
        $this->assertCount(0, Http::recorded());
    }
}
