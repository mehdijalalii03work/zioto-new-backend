<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DigiExpressWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_webhook_rejects_an_invalid_shared_token(): void
    {
        config()->set('services.digiexpress.webhook_token', 'expected-secret');

        $response = $this->postJson('/api/digiexpress/webhook', [
            'shipment_status' => 'InTheRoadBeingDelivering',
            'progress_type' => 'PickedUp',
            'occurred_at' => '2026-11-10 12:00:00',
        ], ['X-DigiExpress-Token' => 'wrong-secret']);

        $response->assertUnauthorized();
        $this->assertDatabaseCount('digiexpress_webhook_events', 0);
    }

    public function test_webhook_persists_a_valid_event_once_even_when_delivered_twice(): void
    {
        config()->set('services.digiexpress.webhook_token', 'expected-secret');
        $payload = [
            'shipping_service_order_id' => 'remote-order-1',
            'shipping_service_shipment_id' => 'remote-shipment-1',
            'shipment_consignor_reference_id' => '21001',
            'occurred_at' => '2026-11-10 12:00:00',
            'shipment_status' => 'InTheRoadBeingDelivering',
            'parcel_status' => 'InTheRoadBeingDelivering',
            'progress_type' => 'PickedUp',
        ];

        $this->postJson('/api/digiexpress/webhook', $payload, [
            'X-DigiExpress-Token' => 'expected-secret',
        ])->assertOk();

        $this->postJson('/api/digiexpress/webhook', $payload, [
            'X-DigiExpress-Token' => 'expected-secret',
        ])->assertOk();

        $this->assertDatabaseCount('digiexpress_webhook_events', 1);
        $this->assertDatabaseHas('digiexpress_webhook_events', [
            'progress_type' => 'PickedUp',
            'shipment_consignor_reference_id' => '21001',
        ]);
    }

    public function test_webhook_rejects_payload_without_status_or_shipment_identity(): void
    {
        config()->set('services.digiexpress.webhook_token', 'expected-secret');

        $this->postJson('/api/digiexpress/webhook', [
            'progress_type' => 'PickedUp',
        ], ['X-DigiExpress-Token' => 'expected-secret'])->assertUnprocessable();

        $this->assertDatabaseCount('digiexpress_webhook_events', 0);
    }
}
