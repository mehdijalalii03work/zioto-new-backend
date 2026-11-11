<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DigiExpressWebhookEvent extends Model
{
    protected $table = 'digiexpress_webhook_events';

    protected $fillable = [
        'fingerprint', 'shipping_service_order_id', 'shipping_service_shipment_id',
        'shipment_consignor_reference_id', 'shipping_service_parcel_id', 'parcel_shipping_code',
        'shipment_status', 'parcel_status', 'progress_type', 'occurred_at', 'payload', 'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'payload' => 'array',
            'processed_at' => 'datetime',
        ];
    }
}
