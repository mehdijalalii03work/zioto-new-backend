<?php

namespace Modules\Order\Models;

use App\Enums\Inventory\InventoryMode;
use App\Enums\Inventory\SerialFulfillmentStatus;
use App\Models\ProductSerial;
use App\Models\SerialEvent;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Product\Models\Product;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'product_id',
        'product_name',
        'product_price',
        'quantity',
        'subtotal',
        'inventory_mode',
        'serial_tracking_required',
        'made_to_order',
        'production_lead_time_text',
        'production_completed_at',
        'serial_fulfillment_status',
    ];

    protected function casts(): array
    {
        return [
            'product_price' => 'decimal:0',
            'subtotal' => 'decimal:0',
            'inventory_mode' => InventoryMode::class,
            'serial_tracking_required' => 'boolean',
            'made_to_order' => 'boolean',
            'production_completed_at' => 'datetime',
            'serial_fulfillment_status' => SerialFulfillmentStatus::class,
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function serials(): HasMany
    {
        return $this->hasMany(ProductSerial::class, 'current_order_item_id');
    }

    public function serialEvents(): HasMany
    {
        return $this->hasMany(SerialEvent::class)->with('serial');
    }
}
