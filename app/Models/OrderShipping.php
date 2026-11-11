<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Order\Models\Order;

class OrderShipping extends Model
{
    protected $table = 'order_shippings';

    protected $fillable = [
        'order_id', 'shipping_method_id', 'shipping_method_name',
        'shipping_cost', 'tax_amount', 'tax_rate',
        'pickup_date', 'tracking_number', 'tracking_url',
        'estimated_delivery_min', 'estimated_delivery_max', 'delivered_at',
        'order_confirmation_sms_sent_at', 'received_health_sms_sent_at',
        'carrier_code', 'provider_status', 'shipping_status', 'provider_status_updated_at', 'provider_shipment_id', 'provider_reference_id', 'provider_error',
        'estimated_capacity_weight_grams', 'estimated_capacity_dimensions_cm',
    ];

    protected function casts(): array
    {
        return [
            'shipping_cost' => 'decimal:0',
            'tax_amount' => 'decimal:0',
            'tax_rate' => 'decimal:2',
            'pickup_date' => 'date',
            'delivered_at' => 'datetime',
            'order_confirmation_sms_sent_at' => 'datetime',
            'received_health_sms_sent_at' => 'datetime',
            'estimated_capacity_dimensions_cm' => 'array',
            'provider_status_updated_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function shippingMethod(): BelongsTo
    {
        return $this->belongsTo(ShippingMethod::class);
    }

    public function digiExpressParcels(): HasMany
    {
        return $this->hasMany(DigiExpressParcel::class);
    }
}
