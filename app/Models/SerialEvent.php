<?php

namespace App\Models;

use App\Enums\Inventory\SerialEventType;
use App\Enums\Inventory\SerialStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Order\Models\Order;
use Modules\Order\Models\OrderItem;

class SerialEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'product_serial_id',
        'order_id',
        'order_item_id',
        'unit_number',
        'event_type',
        'from_status',
        'to_status',
        'actor_id',
        'reason',
        'operation_key',
        'order_number_snapshot',
        'platform_snapshot',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'event_type' => SerialEventType::class,
            'from_status' => SerialStatus::class,
            'to_status' => SerialStatus::class,
            'created_at' => 'datetime',
        ];
    }

    public function serial(): BelongsTo
    {
        return $this->belongsTo(ProductSerial::class, 'product_serial_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
