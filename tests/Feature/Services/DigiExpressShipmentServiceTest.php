<?php

namespace Tests\Feature\Services;

use App\Models\DigiExpressCity;
use App\Models\DigiExpressCityMapping;
use App\Models\OrderShipping;
use App\Models\ShippingMethod;
use App\Models\UserAddress;
use App\Services\DigiExpressShipmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Modules\Order\Models\Order;
use Tests\TestCase;

class DigiExpressShipmentServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_registers_estimated_shipment_once_with_stable_order_reference(): void
    {
        config()->set('services.digiexpress.base_url', 'https://api-demo.digiexpress.ir');
        config()->set('services.digiexpress.username', 'merchant-user');
        config()->set('services.digiexpress.password', 'merchant-password');
        config()->set('services.digiexpress.origin_city_id', '10');
        config()->set('services.digiexpress.origin_address', 'تهران');
        Http::preventStrayRequests();
        Http::fake([
            'api-demo.digiexpress.ir/api/customers/auth/login' => Http::response(['data' => ['access_token' => 'token']]),
            'api-demo.digiexpress.ir/api/v2/shipments' => Http::response(['data' => ['shipment_id' => 'shipment-1', 'shipment_code' => 'S-1']]),
        ]);

        $method = ShippingMethod::query()->where('code', 'digiexpress_address')->firstOrFail();
        $order = Order::factory()->create(['payment_status' => 'paid']);
        $address = UserAddress::factory()->create(['user_id' => $order->user_id]);
        $order->update(['user_address_id' => $address->id]);
        DigiExpressCity::query()->create(['external_id' => '119', 'name' => 'یزد', 'state_name' => 'یزد']);
        DigiExpressCityMapping::query()->create([
            'city_id' => $address->city_id,
            'digiexpress_city_id' => '119',
            'is_verified' => true,
            'is_destination_enabled' => true,
        ]);
        $shipping = OrderShipping::query()->create([
            'order_id' => $order->id,
            'shipping_method_id' => $method->id,
            'shipping_method_name' => 'ارسال به آدرس',
            'shipping_cost' => 100000,
        ]);

        $registered = app(DigiExpressShipmentService::class)->register($order, [
            'weight_grams' => 750,
            'dimensions_cm' => [30, 20, 10],
        ]);
        app(DigiExpressShipmentService::class)->register($order);

        $this->assertSame('shipment-1', $registered->provider_shipment_id);
        $this->assertSame(str_pad((string) $order->id, 15, '0', STR_PAD_LEFT), $registered->provider_reference_id);
        $this->assertDatabaseHas('order_shippings', ['id' => $shipping->id, 'shipping_status' => 'processing']);
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api-demo.digiexpress.ir/api/v2/shipments'
            && $request['consignor_unique_reference_id'] === str_pad((string) $order->id, 15, '0', STR_PAD_LEFT)
            && data_get($request->data(), 'max_shipment_capacity_estimation_for_pre_reservation.parcels_total_estimated_weight_grams') === 750);
        Http::assertSentCount(2);
    }
}
