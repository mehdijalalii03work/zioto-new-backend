<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class PriceHistory extends Model
{
    protected $connection = 'mongodb';

    protected $collection = 'price_history';

    protected $fillable = [
        'type',
        'item_name',
        'sell_price',
        'change_percent',
        'source',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'sell_price' => 'integer',
            'change_percent' => 'float',
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function scopeBoardItems($query)
    {
        return $query->where('type', 'board');
    }

    public function scopeProductPrices($query)
    {
        return $query->where('type', 'product');
    }

    public function scopeForItem($query, string $itemName)
    {
        return $query->where('item_name', $itemName);
    }
}
