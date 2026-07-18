<?php

namespace Modules\Order\Models;

use App\Models\HesabfaSyncLog;
use App\Models\OrderNote;
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

    protected static function booted(): void
    {
        static::created(function (Order $order) {
            $order->update(['order_number' => $order->id]);
        });
    }

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
        'cancel_reason',
    ];

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:0',
            'hesabfa_synced_at' => 'datetime',
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

    public function syncLogs(): HasMany
    {
        return $this->hasMany(HesabfaSyncLog::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(OrderNote::class)->latest();
    }

    public function addNote(string $note, string $type = 'general', bool $isCustomerNote = false): OrderNote
    {
        return $this->notes()->create([
            'note' => $note,
            'type' => $type,
            'is_customer_note' => $isCustomerNote,
        ]);
    }
}
