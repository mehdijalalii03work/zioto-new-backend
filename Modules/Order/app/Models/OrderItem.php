<?php

namespace Modules\Order\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
        'price_board_item',
        'board_price',
        'weight',
        'labor_coefficient',
        'labor_cost',
        'taxes',
        'final_price',
    ];

    protected function casts(): array
    {
        return [
            'product_price' => 'decimal:0',
            'subtotal' => 'decimal:0',
            'board_price' => 'decimal:2',
            'weight' => 'decimal:2',
            'labor_coefficient' => 'decimal:6',
            'labor_cost' => 'decimal:2',
            'taxes' => 'array',
            'final_price' => 'decimal:2',
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
}
