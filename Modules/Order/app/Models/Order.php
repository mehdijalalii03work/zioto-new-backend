<?php

namespace Modules\Order\Models;

use App\Models\HesabfaSyncLog;
use App\Models\OrderNote;
use App\Models\OrderShipping;
use App\Models\User;
use App\Models\UserAddress;
use App\Support\HasTenantScope;
use App\Support\Platform;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Modules\Order\Database\Factories\OrderFactory;
use Modules\Payment\Models\Payment;

class Order extends Model
{
    use HasFactory, HasTenantScope, SoftDeletes;

    protected static function newFactory(): OrderFactory
    {
        return OrderFactory::new();
    }

    protected static function booted(): void
    {
        static::creating(function (Order $order) {
            $platform = $order->platform ?: Platform::fromRequest();
            $order->platform = $platform;

            // Only auto-generate the number when none was provided explicitly
            // (e.g. WP migration, tests set their own).
            if (! empty($order->order_number)) {
                return;
            }

            // Per-platform order numbering: nopay orders get an "N" prefix
            // so numbers never collide between tenants.
            $query = DB::table('orders')->where('platform', $platform);

            if ($platform === Platform::NOPAY) {
                // Strip the "N-" prefix before casting to int, otherwise
                // CAST('N-00001' AS UNSIGNED) always yields 0.
                $query->where('order_number', 'like', 'N-%');
                $maxNumber = $query->max(DB::raw('CAST(SUBSTRING(order_number, 3) AS UNSIGNED)')) ?? 20999;
            } else {
                $maxNumber = $query->max(DB::raw('CAST(order_number AS UNSIGNED)')) ?? 20999;
            }

            $order->order_number = $platform === Platform::NOPAY
                ? 'N-'.str_pad($maxNumber + 1, 5, '0', STR_PAD_LEFT)
                : str_pad($maxNumber + 1, 5, '0', STR_PAD_LEFT);
        });
    }

    protected $fillable = [
        'user_id',
        'platform',
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
        'tapsi_order_id',
        'tapsi_order_number',
        'tapsi_shipment_bundle',
        'tapsi_delivery_method',
        'tapsi_service_fee',
        'tapsi_voucher_fee',
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

    public function orderNotes(): HasMany
    {
        return $this->hasMany(OrderNote::class)->latest();
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function addNote(string $note, string $type = 'general', bool $isCustomerNote = false): OrderNote
    {
        return $this->orderNotes()->create([
            'note' => $note,
            'type' => $type,
            'is_customer_note' => $isCustomerNote,
        ]);
    }
}
