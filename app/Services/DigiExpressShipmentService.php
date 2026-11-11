<?php

namespace App\Services;

use App\Models\DigiExpressCityMapping;
use App\Models\DigiExpressParcel;
use App\Models\OrderShipping;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Modules\Order\Models\Order;
use RuntimeException;

class DigiExpressShipmentService
{
    public function __construct(private readonly DigiExpressService $api) {}

    /** @param array<string, mixed> $preparation */
    public function register(Order $order, array $preparation = []): OrderShipping
    {
        return DB::transaction(function () use ($order, $preparation): OrderShipping {
            $shipping = OrderShipping::query()->where('order_id', $order->id)->lockForUpdate()->firstOrFail();
            if ($shipping->shippingMethod?->code !== 'digiexpress_address') {
                throw ValidationException::withMessages(['shipping' => 'این سفارش با روش ارسال به آدرس ثبت نشده است.']);
            }
            if ($order->payment_status !== 'paid') {
                throw ValidationException::withMessages(['shipping' => 'ثبت مرسوله پس از پرداخت سفارش ممکن است.']);
            }
            if (filled($shipping->provider_shipment_id)) {
                return $shipping;
            }

            $address = $order->address()->with(['city', 'province'])->first();
            $mapping = $address?->city_id ? DigiExpressCityMapping::query()
                ->where('city_id', $address->city_id)->where('is_verified', true)->where('is_destination_enabled', true)->first() : null;
            if (! $address || ! $mapping) {
                throw ValidationException::withMessages(['shipping' => 'نشانی سفارش شهر دارای نگاشت تأییدشده ندارد.']);
            }

            $reference = str_pad((string) $order->id, 15, '0', STR_PAD_LEFT);
            $estimatedMode = Setting::getValue('digiexpress_parcel_mode', 'estimated') === 'estimated';
            $payload = $this->shipmentPayload($order, $address, $mapping, $reference, $preparation, $estimatedMode);

            try {
                $response = $this->api->registerShipment($payload);
                $data = data_get($response, 'data', $response);
                $shipmentId = data_get($data, 'shipment_id') ?? data_get($data, 'id') ?? data_get($data, 'shipping_service_shipment_id');
                if (! filled($shipmentId)) {
                    throw new RuntimeException('پاسخ ثبت مرسوله شناسه مرسوله نداشت.');
                }
                $shipping->forceFill([
                    'carrier_code' => 'digiexpress',
                    'provider_shipment_id' => (string) $shipmentId,
                    'provider_reference_id' => $reference,
                    'provider_status' => data_get($data, 'status'),
                    'shipping_status' => 'processing',
                    'provider_error' => null,
                    'estimated_capacity_weight_grams' => $estimatedMode ? (string) ($preparation['weight_grams'] ?? $order->items->sum(fn ($item) => (float) $item->weight * (int) $item->quantity)) : null,
                    'estimated_capacity_dimensions_cm' => $estimatedMode ? ($preparation['dimensions_cm'] ?? $this->configuredDimensions()) : null,
                ])->save();
                foreach ((array) data_get($data, 'parcel_summaries', []) as $index => $parcel) {
                    $input = $preparation['parcels'][$index] ?? [];
                    DigiExpressParcel::query()->updateOrCreate(
                        ['order_shipping_id' => $shipping->id, 'consignor_reference_id' => (string) (data_get($parcel, 'consignor_reference_id') ?: $reference.'-'.($index + 1))],
                        ['provider_parcel_id' => data_get($parcel, 'parcel_id'), 'parcel_code' => data_get($parcel, 'parcel_code'), 'weight_grams' => (int) data_get($input, 'weight_grams', 1), 'dimensions_cm' => data_get($input, 'dimensionsInCMs', [1, 1, 1]), 'status' => 'registered'],
                    );
                }
            } catch (\Throwable $exception) {
                $shipping->forceFill(['carrier_code' => 'digiexpress', 'provider_reference_id' => $reference, 'shipping_status' => 'manual_review', 'provider_error' => mb_substr($exception->getMessage(), 0, 65000)])->save();
                report($exception);

                return $shipping->refresh();
            }

            return $shipping->refresh();
        });
    }

    /** @param list<array{weight_grams:int,dimensions_cm:list<int>,consignor_reference_id?:string}> $parcels */
    public function addPreparedParcels(OrderShipping $shipping, array $parcels): void
    {
        if (! filled($shipping->provider_shipment_id)) {
            throw ValidationException::withMessages(['parcels' => 'ابتدا مرسوله را ثبت کنید.']);
        }
        $response = $this->api->addParcel($shipping->provider_shipment_id, ['parcels' => $parcels]);
        $created = data_get($response, 'data.new_added_parcels', data_get($response, 'new_added_parcels', []));
        foreach ($created as $index => $parcel) {
            $input = $parcels[$index] ?? [];
            DigiExpressParcel::query()->updateOrCreate(
                ['order_shipping_id' => $shipping->id, 'consignor_reference_id' => (string) ($input['consignor_reference_id'] ?? $shipping->provider_reference_id.'-'.($index + 1))],
                ['provider_parcel_id' => data_get($parcel, 'parcel_id'), 'parcel_code' => data_get($parcel, 'parcel_code'), 'weight_grams' => $input['weight_grams'], 'dimensions_cm' => $input['dimensionsInCMs'], 'status' => data_get($parcel, 'status')],
            );
        }
    }

    public function downloadParcelLabel(DigiExpressParcel $parcel): string
    {
        if (! filled($parcel->provider_parcel_id) && ! filled($parcel->parcel_code)) {
            throw new RuntimeException('شناسه پارسل برای دریافت لیبل ثبت نشده است.');
        }
        $contents = $this->api->label((string) ($parcel->provider_parcel_id ?: $parcel->parcel_code));
        $path = 'digiexpress/labels/'.$parcel->order_shipping_id.'/'.$parcel->id.'.pdf';
        Storage::disk('local')->put($path, $contents);
        $parcel->forceFill(['label_path' => $path])->save();

        return $path;
    }

    public function cancel(OrderShipping $shipping): void
    {
        if (! filled($shipping->provider_shipment_id)) {
            throw ValidationException::withMessages(['shipping' => 'مرسوله ثبت‌شده‌ای برای لغو وجود ندارد.']);
        }
        $this->api->cancelShipment($shipping->provider_shipment_id);
        $shipping->forceFill(['shipping_status' => 'cancelled', 'provider_status_updated_at' => now()])->save();
    }

    /** @param array<string, mixed> $preparation @return array<string, mixed> */
    private function shipmentPayload(Order $order, object $address, DigiExpressCityMapping $mapping, string $reference, array $preparation, bool $estimatedMode): array
    {
        $payload = [
            'consignor_unique_reference_id' => $reference,
            'cargo_ready_date_time' => now()->addDay()->format('Y-m-d H:i:s'),
            'origin_point_id' => config('services.digiexpress.origin_point_id'),
            'origin_address' => [
                'city_id' => (string) config('services.digiexpress.origin_city_id'),
                'full_address' => config('services.digiexpress.origin_address'),
                'building_number' => config('services.digiexpress.origin_building_number'),
                'postal_code' => config('services.digiexpress.origin_postal_code'),
                'latitude' => config('services.digiexpress.origin_latitude'),
                'longitude' => config('services.digiexpress.origin_longitude'),
            ],
            'cargo_submitter_person' => [
                'first_name' => config('services.digiexpress.sender_first_name'),
                'last_name' => config('services.digiexpress.sender_last_name'),
                'national_code' => config('services.digiexpress.sender_national_code'),
                'first_mobile' => config('services.digiexpress.sender_mobile'),
                'phone' => config('services.digiexpress.sender_phone'),
            ],
            'destination_address' => [
                'city_id' => (string) $mapping->digiexpress_city_id,
                'full_address' => $address->full_address,
                'building_number' => $address->plate,
                'unit' => $address->unit,
                'postal_code' => $address->postal_code,
                'latitude' => $address->latitude,
                'longitude' => $address->longitude,
            ],
            'recipient_person' => [
                'first_name' => $address->receiver_name ?: $order->user?->name,
                'last_name' => '',
                'national_code' => $address->receiver_national_code,
                'first_mobile' => $address->receiver_phone ?: $order->user?->phone,
                'phone' => $address->receiver_phone ?: $order->user?->phone,
            ],
            'revive_if_already_registered_and_failed' => true,
        ];

        if ($estimatedMode) {
            $dimensions = $preparation['dimensions_cm'] ?? $this->configuredDimensions();
            $weight = $preparation['weight_grams'] ?? (int) round($order->items->sum(fn ($item) => (float) $item->weight * (int) $item->quantity));
            if ($weight < 1 || count($dimensions) !== 3 || min($dimensions) < 1) {
                throw ValidationException::withMessages(['preparation' => 'وزن و ابعاد تخمینی بسته باید معتبر باشند.']);
            }
            $payload['max_shipment_capacity_estimation_for_pre_reservation'] = [
                'parcels_total_estimated_weight_grams' => $weight,
                'all_parcels_as_single_box_estimated_max_dimensions_cm' => array_values($dimensions),
                'each_parcel_size_tier' => 'NORMAL',
            ];
        } else {
            $parcels = $preparation['parcels'] ?? [];
            if ($parcels === []) {
                throw ValidationException::withMessages(['parcels' => 'در حالت پارسل مشخص، حداقل یک بسته باید وارد شود.']);
            }
            $payload['parcels'] = array_map(fn (array $parcel): array => $parcel + [
                'content_value_price_rials' => (string) (int) $order->total_amount,
                'characteristics' => ['normal'],
                'packaging_type' => 'box',
                'items_description' => 'سفارش اینترنتی',
            ], $parcels);
        }

        return $payload;
    }

    /** @return list<int> */
    private function configuredDimensions(): array
    {
        return [
            (int) Setting::getValue('digiexpress_estimated_length_cm', 1),
            (int) Setting::getValue('digiexpress_estimated_width_cm', 1),
            (int) Setting::getValue('digiexpress_estimated_height_cm', 1),
        ];
    }
}
