<?php

namespace App\Models;

use App\Enums\Inventory\SerialStatus;
use Database\Factories\ProductSerialFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Order\Models\OrderItem;
use Modules\Product\Models\Product;

class ProductSerial extends Model
{
    /** @use HasFactory<ProductSerialFactory> */
    use HasFactory;

    protected static function newFactory(): ProductSerialFactory
    {
        return ProductSerialFactory::new();
    }

    protected $fillable = [
        'product_id',
        'serial_code',
        'serial_prefix',
        'import_row_id',
        'status',
        'current_order_item_id',
        'unit_number',
        'reserved_until',
        'allocated_at',
        'shipped_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => SerialStatus::class,
            'reserved_until' => 'datetime',
            'allocated_at' => 'datetime',
            'shipped_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function importRow(): BelongsTo
    {
        return $this->belongsTo(SerialImportRow::class, 'import_row_id');
    }

    public function currentOrderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class, 'current_order_item_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(SerialEvent::class);
    }
}
