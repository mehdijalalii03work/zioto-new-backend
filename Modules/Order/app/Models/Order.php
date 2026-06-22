<?php

namespace Modules\Order\Models;

use App\Models\OrderShipping;
use App\Models\User;
use App\Models\UserAddress;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'order_number',
        'status',
        'total_amount',
        'payment_method',
        'payment_status',
        'shipping_address',
        'user_address_id',
        'shipping_address_snapshot',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:0',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function address(): BelongsTo
    {
        return $this->belongsTo(UserAddress::class, 'user_address_id');
    }

    public function shipping(): HasOne
    {
        return $this->hasOne(OrderShipping::class);
    }
}
