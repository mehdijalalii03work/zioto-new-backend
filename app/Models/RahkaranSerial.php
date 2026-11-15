<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Order\Models\OrderItem;
use Modules\Product\Models\Product;

class RahkaranSerial extends Model
{
    protected $fillable = ['product_id', 'order_item_id', 'serial', 'status', 'synced_at'];

    protected function casts(): array
    {
        return ['synced_at' => 'datetime'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }
}
