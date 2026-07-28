<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Order\Models\Order;

class OrderShipping extends Model
{
    protected $table = 'order_shippings';

    protected $fillable = [
        'order_id', 'shipping_method_id', 'shipping_method_name',
        'shipping_cost', 'tax_amount', 'tax_rate',
        'pickup_date', 'tracking_number', 'tracking_url',
        'estimated_delivery_min', 'estimated_delivery_max', 'delivered_at',
    ];

    protected function casts(): array
    {
        return [
            'shipping_cost' => 'decimal:0',
            'tax_amount' => 'decimal:0',
            'tax_rate' => 'decimal:2',
            'pickup_date' => 'date',
            'delivered_at' => 'datetime',
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
}
