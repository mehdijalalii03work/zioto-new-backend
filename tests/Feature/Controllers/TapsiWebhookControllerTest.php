<?php

namespace Tests\Feature\Controllers;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Order\Models\Order;
use Tests\TestCase;

class TapsiWebhookControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['tapsi.enabled' => true]);
        config(['tapsi.auth_token' => 'test-token-123']);
    }

    private function validPayload(array $overrides = []): array
    {
        return array_replace_recursive([
            'orderDetail' => [
                'orderId' => 999999999,
                'changeType' => 1,
                'createdOnTimestamp' => '2026-08-23T11:49:40.36241+03:30',
                'receiverFullName' => 'علی رضایی',
                'customerFullName' => 'علی رضایی',
                'latitude' => 35.77,
                'longitude' => 51.36,
                'deliveryAddress' => 'تهران، خیابان ولیعصر',
                'customerMobile' => '09121234567',
                'customerNationalCode' => '1234567890',
                'receiverMobile' => '09121234567',
                'orderNumber' => '405060999999',
                'customerFirstName' => 'علی',
                'customerLastName' => 'رضایی',
                'storeId' => 1507676090628616192,
            ],
            'items' => [
                [
                    'requestId' => 111111,
                    'orderItemId' => 222222,
                    'orderId' => 999999999,
                    'tapsiShopProductId' => 333333,
                    'productId' => 'ZGB5-0000-5',
                    'quantity' => -1,
                    'changeType' => 1,
                    'createdOnTimestamp' => '2026-08-23T11:49:40.36241+03:30',
                    'receiverFullName' => 'علی رضایی',
                    'customerFullName' => 'علی رضایی',
                    'latitude' => 35.77,
                    'longitude' => 51.36,
                    'deliveryAddress' => 'تهران، خیابان ولیعصر',
                    'finalPrice' => 100000000,
                    'originalPrice' => 100000000,
                    'customerMobile' => '09121234567',
                    'customerNationalCode' => '1234567890',
                    'receiverMobile' => '09121234567',
                    'cityName' => 'تهران',
                    'provinceName' => 'تهران',
                    'postalCode' => '1234567890',
                ],
            ],
        ], $overrides);
    }

    public function test_webhook_creates_order(): void
    {
        $this->postJson('/api/tapsi/webhook', $this->validPayload(), [
            'Tapsi-Shop-Hub-Authorization' => 'test-token-123',
        ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('orders', [
            'platform' => 'tapsi',
            'tapsi_order_id' => '999999999',
            'order_number' => '405060999999',
            'status' => 'pending',
            'payment_status' => 'paid',
            'total_amount' => 100000000,
        ]);
    }

    public function test_webhook_creates_user(): void
    {
        $this->postJson('/api/tapsi/webhook', $this->validPayload(), [
            'Tapsi-Shop-Hub-Authorization' => 'test-token-123',
        ])->assertOk();

        $this->assertDatabaseHas('users', [
            'phone' => '09121234567',
            'name' => 'علی رضایی',
            'first_name' => 'علی',
            'last_name' => 'رضایی',
            'national_code' => '1234567890',
            'platform' => 'tapsi',
        ]);
    }

    public function test_webhook_creates_order_items(): void
    {
        $this->postJson('/api/tapsi/webhook', $this->validPayload(), [
            'Tapsi-Shop-Hub-Authorization' => 'test-token-123',
        ])->assertOk();

        $order = Order::withoutTenantScope()->where('tapsi_order_id', '999999999')->first();
        $this->assertNotNull($order);
        $this->assertCount(1, $order->items);
        $this->assertEquals('ZGB5-0000-5', $order->items[0]->product_name);
        $this->assertEquals(1, $order->items[0]->quantity);
        $this->assertEquals(100000000, $order->items[0]->subtotal);
    }

    public function test_webhook_handles_duplicate(): void
    {
        $payload = $this->validPayload();
        $headers = ['Tapsi-Shop-Hub-Authorization' => 'test-token-123'];

        $this->postJson('/api/tapsi/webhook', $payload, $headers)->assertOk();
        $this->postJson('/api/tapsi/webhook', $payload, $headers)
            ->assertOk()
            ->assertJson(['message' => 'Duplicate']);

        $this->assertEquals(1, Order::withoutTenantScope()->where('tapsi_order_id', '999999999')->count());
    }

    public function test_webhook_rejects_invalid_token(): void
    {
        $this->postJson('/api/tapsi/webhook', $this->validPayload(), [
            'Tapsi-Shop-Hub-Authorization' => 'wrong-token',
        ])->assertStatus(401);
    }

    public function test_webhook_accepts_valid_token(): void
    {
        $this->postJson('/api/tapsi/webhook', $this->validPayload(), [
            'Tapsi-Shop-Hub-Authorization' => 'test-token-123',
        ])->assertOk();
    }

    public function test_webhook_ignores_non_create_event(): void
    {
        $payload = $this->validPayload();
        $payload['orderDetail']['changeType'] = 2;

        $this->postJson('/api/tapsi/webhook', $payload, [
            'Tapsi-Shop-Hub-Authorization' => 'test-token-123',
        ])
            ->assertOk()
            ->assertJson(['message' => 'Event ignored']);

        $this->assertDatabaseMissing('orders', [
            'tapsi_order_id' => '999999999',
        ]);
    }

    public function test_webhook_stores_customer_info_in_notes(): void
    {
        $this->postJson('/api/tapsi/webhook', $this->validPayload(), [
            'Tapsi-Shop-Hub-Authorization' => 'test-token-123',
        ])->assertOk();

        $order = Order::withoutTenantScope()->where('tapsi_order_id', '999999999')->first();
        $notes = json_decode($order->notes, true);

        $this->assertEquals('علی رضایی', $notes['customer_name']);
        $this->assertEquals('علی', $notes['customer_first_name']);
        $this->assertEquals('رضایی', $notes['customer_last_name']);
        $this->assertEquals('09121234567', $notes['customer_phone']);
        $this->assertEquals('1234567890', $notes['customer_national_code']);
        $this->assertEquals('تهران', $notes['city']);
        $this->assertEquals('تهران', $notes['province']);
    }

    public function test_webhook_returns_400_without_order_detail(): void
    {
        $this->postJson('/api/tapsi/webhook', ['items' => []], [
            'Tapsi-Shop-Hub-Authorization' => 'test-token-123',
        ])->assertStatus(400);
    }

    public function test_webhook_returns_503_when_disabled(): void
    {
        config(['tapsi.enabled' => false]);

        $this->postJson('/api/tapsi/webhook', $this->validPayload(), [
            'Tapsi-Shop-Hub-Authorization' => 'test-token-123',
        ])->assertStatus(503);
    }

    public function test_webhook_accepts_legacy_header_tapsishopauthorization(): void
    {
        $this->postJson('/api/tapsi/webhook', $this->validPayload(), [
            'tapsishopauthorization' => 'test-token-123',
        ])->assertOk();
    }

    public function test_webhook_accepts_legacy_header_tapsishop_hub_webhook_authorization(): void
    {
        $this->postJson('/api/tapsi/webhook', $this->validPayload(), [
            'tapsishop-hub-webhook-authorization' => 'test-token-123',
        ])->assertOk();
    }
}
