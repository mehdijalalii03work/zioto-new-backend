<?php

namespace App\Models;

use App\Enums\Inventory\StockReservationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Order\Models\OrderItem;
use Modules\Payment\Models\Payment;
use Modules\Product\Models\Product;

class ProductStockReservation extends Model
{
    protected $fillable = [
        'order_item_id',
        'product_id',
        'payment_id',
        'quantity',
        'status',
        'reserved_until',
    ];

    protected function casts(): array
    {
        return [
            'status' => StockReservationStatus::class,
            'reserved_until' => 'datetime',
        ];
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
