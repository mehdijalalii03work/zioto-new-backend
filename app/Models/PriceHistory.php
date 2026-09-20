<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class PriceHistory extends Model
{
    protected $connection = 'mongodb';

    protected $collection = 'price_history';

    protected $fillable = [
        'type',
        'source',
        'items_count',
        'data',
    ];

    protected function casts(): array
    {
        return [
            'items_count' => 'integer',
            'data' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function scopeBoard($query)
    {
        return $query->where('type', 'board');
    }

    public function scopeProduct($query)
    {
        return $query->where('type', 'product');
    }
}
