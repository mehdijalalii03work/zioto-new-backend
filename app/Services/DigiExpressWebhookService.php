<?php

namespace App\Services;

use App\Models\DigiExpressWebhookEvent;
use App\Models\OrderShipping;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DigiExpressWebhookService
{
    /** @param array<string, mixed> $payload */
    public function receive(array $payload): void
    {
        $encodedPayload = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $fingerprint = hash('sha256', $encodedPayload);
        $occurredAt = $payload['occurred_at'] ?? null;
        $occurredAt = is_string($occurredAt) && $occurredAt !== '' ? $occurredAt : null;

        DB::transaction(function () use ($payload, $encodedPayload, $fingerprint, $occurredAt): void {
            $inserted = DB::table('digiexpress_webhook_events')->insertOrIgnore([
                'fingerprint' => $fingerprint,
                'shipping_service_order_id' => $payload['shipping_service_order_id'] ?? null,
                'shipping_service_shipment_id' => $payload['shipping_service_shipment_id'] ?? null,
                'shipment_consignor_reference_id' => $payload['shipment_consignor_reference_id'] ?? null,
                'shipping_service_parcel_id' => $payload['shipping_service_parcel_id'] ?? null,
                'parcel_shipping_code' => $payload['parcel_shipping_code'] ?? null,
                'shipment_status' => $payload['shipment_status'] ?? null,
                'parcel_status' => $payload['parcel_status'] ?? null,
                'progress_type' => $payload['progress_type'] ?? null,
                'occurred_at' => $occurredAt,
                'payload' => $encodedPayload,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($inserted === 0) {
                return;
            }

            $event = DigiExpressWebhookEvent::query()->where('fingerprint', $fingerprint)->firstOrFail();
            if ($this->applyToOrderShipping($event)) {
                $event->forceFill(['processed_at' => now()])->save();
            }
        });
    }

    public function replayUnprocessed(int $limit = 100): int
    {
        $processed = 0;
        DigiExpressWebhookEvent::query()->whereNull('processed_at')->oldest('id')->limit($limit)->get()->each(function (DigiExpressWebhookEvent $event) use (&$processed): void {
            DB::transaction(function () use ($event, &$processed): void {
                $locked = DigiExpressWebhookEvent::query()->lockForUpdate()->find($event->id);
                if ($locked && $locked->processed_at === null && $this->applyToOrderShipping($locked)) {
                    $locked->forceFill(['processed_at' => now()])->save();
                    $processed++;
                }
            });
        });

        return $processed;
    }

    /** @param array<string, mixed> $feed @return int Number of distinct events accepted */
    public function ingestFeed(array $feed): int
    {
        $events = [];
        $this->collectFeedEvents($feed, $events);
        foreach ($events as $payload) {
            $this->receive($payload);
        }

        return count($events);
    }

    /** @return Collection<int, DigiExpressWebhookEvent> */
    public function recentEventsForOrder(int $orderId): Collection
    {
        return DigiExpressWebhookEvent::query()
            ->where('shipment_consignor_reference_id', (string) $orderId)
            ->orWhereIn('shipping_service_shipment_id', OrderShipping::query()->where('order_id', $orderId)->whereNotNull('provider_shipment_id')->select('provider_shipment_id'))
            ->latest('created_at')->limit(100)->get();
    }

    private function applyToOrderShipping(DigiExpressWebhookEvent $event): bool
    {
        $shipping = filled($event->shipment_consignor_reference_id) || filled($event->shipping_service_shipment_id) ? OrderShipping::query()
            ->whereHas('shippingMethod', fn ($query) => $query->where('code', 'digiexpress_address'))
            ->where(function ($query) use ($event): void {
                if (filled($event->shipment_consignor_reference_id)) {
                    $query->where('provider_reference_id', $event->shipment_consignor_reference_id);
                }
                if (filled($event->shipping_service_shipment_id)) {
                    $method = filled($event->shipment_consignor_reference_id) ? 'orWhere' : 'where';
                    $query->{$method}('provider_shipment_id', $event->shipping_service_shipment_id);
                }
            })
            ->first() : null;

        if (! $shipping && ctype_digit((string) $event->shipment_consignor_reference_id)) {
            $localOrderId = (int) $event->shipment_consignor_reference_id;
            $shipping = OrderShipping::query()
                ->whereHas('shippingMethod', fn ($query) => $query->where('code', 'digiexpress_address'))
                ->whereHas('order', fn ($query) => $query->whereKey($localOrderId))
                ->first();
        }

        if (! $shipping || $shipping->shipping_status === 'manual_fallback') {
            return false;
        }

        $occurredAt = $event->occurred_at;
        $isNewerEvent = $occurredAt === null
            || $shipping->provider_status_updated_at === null
            || $occurredAt->greaterThanOrEqualTo($shipping->provider_status_updated_at);

        $updates = array_filter([
            'carrier_code' => $shipping->carrier_code ?: 'digiexpress',
            'provider_shipment_id' => $event->shipping_service_shipment_id,
            'provider_reference_id' => $event->shipment_consignor_reference_id,
        ], fn (mixed $value): bool => $value !== null);

        if ($isNewerEvent) {
            $updates['provider_status'] = $event->shipment_status ?? $event->parcel_status;
            $updates['shipping_status'] = $this->normalizedStatus($event);
            $updates['provider_status_updated_at'] = $occurredAt ?? now();
            $updates['provider_error'] = $this->failureReason($event->payload);

            if ($updates['shipping_status'] === 'delivered') {
                $updates['delivered_at'] = $occurredAt ?? now();
            }
        }

        $shipping->forceFill($updates)->save();

        if ($event->shipping_service_parcel_id || $event->parcel_shipping_code) {
            $shipping->digiExpressParcels()
                ->where(function ($query) use ($event): void {
                    $query->when($event->shipping_service_parcel_id, fn ($query) => $query->where('provider_parcel_id', $event->shipping_service_parcel_id))
                        ->when($event->parcel_shipping_code, fn ($query) => $query->orWhere('parcel_code', $event->parcel_shipping_code));
                })
                ->update(['status' => $event->parcel_status, 'updated_at' => now()]);
        }

        return true;
    }

    /** @param array<string, mixed>|list<mixed> $node @param list<array<string, mixed>> $events */
    private function collectFeedEvents(array $node, array &$events): void
    {
        if (isset($node['progress_type']) && (isset($node['shipment_status']) || isset($node['parcel_status']))) {
            $events[] = $node;

            return;
        }

        if (isset($node['payload'])) {
            $payload = $node['payload'];
            if (is_string($payload)) {
                $payload = json_decode($payload, true);
            }
            if (is_array($payload) && isset($payload['progress_type'])) {
                $events[] = $payload;
            }
        }

        foreach ($node as $value) {
            if (is_array($value)) {
                $this->collectFeedEvents($value, $events);
            }
        }
    }

    private function normalizedStatus(DigiExpressWebhookEvent $event): string
    {
        $rawStatus = (string) ($event->shipment_status ?? $event->parcel_status ?? '');
        $status = Str::lower($rawStatus);

        if (str_contains($status, 'deliver') && (str_contains($status, 'success') || str_contains($status, 'customer') || $status === 'delivered')) {
            return 'delivered';
        }

        if (str_contains($status, 'cancel')) {
            return 'cancelled';
        }

        if (str_contains($status, 'fail') || str_contains($status, 'idle') || str_contains($status, 'noroute')) {
            return 'failed';
        }

        if (str_contains($status, 'intheroad') || str_contains($status, 'beingdeliver')) {
            return 'in_transit';
        }

        if (str_contains($status, 'awaitingforselectedroute')) {
            return 'awaiting_pickup';
        }

        if (str_contains($status, 'awaitingforrouteselection')) {
            return 'route_found';
        }

        if (str_contains($status, 'awaitingforcandidateroutes')) {
            return 'route_searching';
        }

        return 'processing';
    }

    private function failureReason(array $payload): ?string
    {
        foreach (['pickup_failure_reason', 'last_mile_failure_reason', 'return_failure_reason'] as $key) {
            if (filled($payload[$key] ?? null)) {
                return mb_substr((string) $payload[$key], 0, 65000);
            }
        }

        return null;
    }
}
