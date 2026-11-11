<?php

namespace Tests\Feature\Services;

use App\Models\City;
use App\Models\DigiExpressCityMapping;
use App\Models\Province;
use App\Services\DigiExpressCitySyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DigiExpressCitySyncServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_city_sync_creates_an_unverified_exact_match_suggestion(): void
    {
        $province = Province::factory()->create(['name' => 'یزد']);
        $city = City::factory()->create([
            'province_id' => $province->id,
            'name' => 'يَزد',
        ]);
        config()->set('services.digiexpress.base_url', 'https://api-demo.digiexpress.ir');
        config()->set('services.digiexpress.username', 'merchant-user');
        config()->set('services.digiexpress.password', 'merchant-password');
        Http::preventStrayRequests();
        Http::fake([
            'api-demo.digiexpress.ir/api/customers/auth/login' => Http::response([
                'data' => ['access_token' => 'access-token'],
            ]),
            'api-demo.digiexpress.ir/api/cities' => Http::response([
                'data' => [
                    'cities' => [[
                        'id' => 119,
                        'name' => 'یزد',
                        'state_id' => 31,
                        'state_name' => 'یَزد',
                        'latitude' => 31.8974,
                        'longitude' => 54.3569,
                    ]],
                ],
            ]),
        ]);

        $syncedCount = app(DigiExpressCitySyncService::class)->sync();
        $mapping = DigiExpressCityMapping::query()->where('digiexpress_city_id', '119')->firstOrFail();

        $this->assertSame(1, $syncedCount);
        $this->assertSame($city->id, $mapping->suggested_city_id);
        $this->assertNull($mapping->city_id);
        $this->assertFalse($mapping->is_verified);
        $this->assertFalse($mapping->is_destination_enabled);
    }

    public function test_city_sync_does_not_suggest_when_local_name_match_is_ambiguous(): void
    {
        $province = Province::factory()->create(['name' => 'یزد']);
        City::factory()->count(2)->create(['province_id' => $province->id, 'name' => 'یزد']);
        config()->set('services.digiexpress.base_url', 'https://api-demo.digiexpress.ir');
        config()->set('services.digiexpress.username', 'merchant-user');
        config()->set('services.digiexpress.password', 'merchant-password');
        Http::preventStrayRequests();
        Http::fake([
            'api-demo.digiexpress.ir/api/customers/auth/login' => Http::response(['data' => ['access_token' => 'access-token']]),
            'api-demo.digiexpress.ir/api/cities' => Http::response(['data' => ['cities' => [[
                'id' => 119,
                'name' => 'یزد',
                'state_id' => 31,
                'state_name' => 'یزد',
            ]]]]),
        ]);

        app(DigiExpressCitySyncService::class)->sync();

        $mapping = DigiExpressCityMapping::query()->where('digiexpress_city_id', '119')->firstOrFail();
        $this->assertNull($mapping->suggested_city_id);
        $this->assertFalse($mapping->is_verified);
        $this->assertFalse($mapping->is_destination_enabled);
    }
}
