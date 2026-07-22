<?php

namespace Modules\Payment\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Order\Models\Order;

class Payment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'order_id',
        'transaction_id',
        'amount',
        'payment_method',
        'status',
        'gateway',
        'gateway_response',
        'description',
        'paid_at',
        'national_code',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:0',
            'gateway_response' => 'array',
            'paid_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
