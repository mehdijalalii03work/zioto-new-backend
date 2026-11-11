<?php

namespace Tests\Feature\Services;

use App\Services\DigiExpressService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DigiExpressServiceTest extends TestCase
{
    public function test_city_sync_authenticates_and_reads_the_documented_cities_endpoint(): void
    {
        config()->set('services.digiexpress.base_url', 'https://api-demo.digiexpress.ir');
        config()->set('services.digiexpress.username', 'merchant-user');
        config()->set('services.digiexpress.password', 'merchant-password');
        Http::preventStrayRequests();
        Http::fake([
            'api-demo.digiexpress.ir/api/customers/auth/login' => Http::response([
                'status_code' => 200,
                'data' => [
                    'access_token' => 'access-token',
                    'refresh_token' => 'refresh-token',
                ],
            ]),
            'api-demo.digiexpress.ir/api/cities' => Http::response([
                'status_code' => 200,
                'data' => [
                    'cities' => [[
                        'id' => 119,
                        'name' => 'یزد',
                        'state_id' => 31,
                        'state_name' => 'یزد',
                        'latitude' => 31.8974,
                        'longitude' => 54.3569,
                    ]],
                ],
            ]),
        ]);

        $cities = app(DigiExpressService::class)->getCities();

        $this->assertSame(119, $cities[0]['id']);
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api-demo.digiexpress.ir/api/customers/auth/login'
            && $request['username'] === 'merchant-user'
            && $request['password'] === 'merchant-password'
        );
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api-demo.digiexpress.ir/api/cities'
            && $request->header('Authorization') === ['access-token']
        );
    }
}
