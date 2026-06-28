<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Order\Models\Order;

class OrderNote extends Model
{
    protected $fillable = [
        'order_id',
        'type',
        'note',
        'is_customer_note',
    ];

    protected function casts(): array
    {
        return [
            'is_customer_note' => 'boolean',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
