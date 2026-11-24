<?php

namespace Tests\Feature\Services;

use App\Services\RahkaranService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RahkaranServiceTest extends TestCase
{
    private const string BASE_URL = 'https://rahkaran.example.test';

    private const string AUTH_URL = self::BASE_URL.'/Framework/Services/AuthenticationService.svc';

    private const string STOCK_URL = self::BASE_URL.'/Sales/SalesDocument/Services/SalesDocumentService.svc/productInventoryRemainingValue';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'rahkaran.base_url' => self::BASE_URL,
            'rahkaran.username' => 'test-user',
            'rahkaran.password' => 'test-password',
        ]);
    }

    public function test_stock_requests_reuse_the_cookie_name_issued_by_rahkaran(): void
    {
        Cache::put('rahkaran:session-cookie', 'legacy-value-without-cookie-name');
        Http::preventStrayRequests();
        Http::fake([
            self::AUTH_URL.'/session' => Http::response($this->sessionPayload()),
            self::AUTH_URL.'/login' => Http::sequence()->push([], 200, [
                'Set-Cookie' => ['sg-dummy=ignored; Path=/', 'sg-auth-sg=issued-value; Path=/'],
            ]),
            self::STOCK_URL => Http::response(['ActualRemainingValue' => 42]),
        ]);

        $service = app(RahkaranService::class);
        $service->getStock(580, 1, 1);
        $stock = $service->getStock(581, 1, 1);

        $this->assertSame(42.0, $stock);
        $this->assertSame(['sg-auth-sg=issued-value', 'sg-auth-sg=issued-value'], $this->sentCookies(self::STOCK_URL));
        Http::assertSentCount(4);
    }

    #[DataProvider('rejectedRequests')]
    public function test_rejected_requests_retry_with_the_newly_issued_cookie(string $endpoint, int $status): void
    {
        Http::preventStrayRequests();
        Http::fake([
            self::AUTH_URL.'/session' => Http::response($this->sessionPayload()),
            self::AUTH_URL.'/login' => Http::sequence()
                ->push([], 200, ['Set-Cookie' => 'sg-auth=expired-value; Path=/'])
                ->push([], 200, ['Set-Cookie' => 'sg-auth-sg=renewed-value; Path=/']),
            $endpoint => Http::sequence()
                ->pushStatus($status)
                ->push(['ActualRemainingValue' => 42]),
        ]);

        $service = app(RahkaranService::class);
        $result = $endpoint === self::STOCK_URL
            ? $service->getStock(580, 1, 1)
            : $service->testConnection()['success'];

        $this->assertSame($endpoint === self::STOCK_URL ? 42.0 : true, $result);
        $this->assertSame(['sg-auth=expired-value', 'sg-auth-sg=renewed-value'], $this->sentCookies($endpoint));
        Http::assertSentCount(6);
    }

    public static function rejectedRequests(): array
    {
        return [
            'stock returns 401' => [self::STOCK_URL, 401],
            'connection test returns 403' => [self::AUTH_URL.'/now', 403],
        ];
    }

    public function test_serials_are_read_from_the_serial_tracking_factor_in_the_selected_inventory(): void
    {
        config(['rahkaran.inventory_id' => 21]);
        Http::preventStrayRequests();
        Cache::put('rahkaran:session-cookies', ['sg-auth-sg' => 'test-session']);
        $url = self::BASE_URL.'/Sales/SalesDocument/Services/SalesDocumentService.svc/products';
        Http::fake([$url => Http::response([[
            'id' => 580,
            'trackingFactors' => [
                'structure' => [['title' => 'بچ'], ['title' => 'سریال']],
                'values' => [
                    ['values' => ['batch', '00123'], 'remaining' => ['physical' => 0.5]],
                    ['values' => ['batch', '00123'], 'remaining' => ['physical' => 0.5]],
                    ['values' => ['batch', 'unavailable'], 'remaining' => ['physical' => 0]],
                    ['values' => ['batch', ''], 'remaining' => ['physical' => 1]],
                ],
            ],
        ]])]);

        $serials = app(RahkaranService::class)->getSerialsForPart(580);

        $this->assertSame([['Serial' => '00123']], $serials);
        Http::assertSent(fn (Request $request): bool => $request->url() === $url
            && $request->method() === 'POST'
            && $request['ProductIDs'] === [580]
            && $request['InventoryID'] === 21
            && $request['CalculateRemaining'] === true
            && $request['IncludeTrackingFactors'] === true);
    }

    public function test_serials_for_multiple_products_are_read_in_one_request(): void
    {
        config(['rahkaran.inventory_id' => 21]);
        Http::preventStrayRequests();
        Cache::put('rahkaran:session-cookies', ['sg-auth-sg' => 'test-session']);
        $url = self::BASE_URL.'/Sales/SalesDocument/Services/SalesDocumentService.svc/products';
        Http::fake([$url => Http::response([
            ['id' => 580, 'trackingFactors' => [
                'structure' => [['title' => 'سریال']],
                'values' => [['values' => ['00123'], 'remaining' => ['physical' => 1]]],
            ]],
            ['id' => 581, 'trackingFactors' => ['structure' => [['title' => 'سریال']], 'values' => []]],
        ])]);

        $result = app(RahkaranService::class)->getSerialsForParts([580, 581, 582]);

        $this->assertSame([580 => [['Serial' => '00123']], 581 => []], $result);
        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request): bool => $request['ProductIDs'] === [580, 581, 582]
            && $request['PageSize'] === 3);
    }

    public function test_a_missing_product_in_the_serial_response_raises_an_error(): void
    {
        Http::preventStrayRequests();
        Cache::put('rahkaran:session-cookies', ['sg-auth-sg' => 'test-session']);
        Http::fake([self::BASE_URL.'/Sales/SalesDocument/Services/SalesDocumentService.svc/products' => Http::response([])]);

        $this->expectException(\RuntimeException::class);

        app(RahkaranService::class)->getSerialsForPart(580);
    }

    /**
     * @return array{id: string, rsa: array{M: string, E: string}}
     */
    private function sessionPayload(): array
    {
        $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048]);
        $rsa = openssl_pkey_get_details($key)['rsa'];

        return ['id' => 'test-session', 'rsa' => ['M' => bin2hex($rsa['n']), 'E' => bin2hex($rsa['e'])]];
    }

    /**
     * @return list<string>
     */
    private function sentCookies(string $url): array
    {
        return Http::recorded(fn (Request $request): bool => $request->url() === $url)
            ->map(fn (array $record): string => $record[0]->header('Cookie')[0] ?? '')
            ->values()
            ->all();
    }
}
