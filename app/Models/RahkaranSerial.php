<?php

namespace App\Models;

use App\Support\InventorySource;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Order\Models\OrderItem;
use Modules\Product\Models\Product;

class RahkaranSerial extends Model
{
    protected $fillable = ['product_id', 'order_item_id', 'serial', 'status', 'synced_at', 'origin'];

    protected function casts(): array
    {
        return ['synced_at' => 'datetime'];
    }

    #[Scope]
    protected function forCurrentInventory(Builder $query): void
    {
        if (! InventorySource::isOffline()) {
            return;
        }

        $origin = setting('offline_serial_origin', 'combined');
        if (in_array($origin, ['api', 'excel'], true)) {
            $query->where('origin', $origin);
        }
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
