<?php

namespace App\Models;

use App\Support\HasTenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserAddress extends Model
{
    use HasFactory, HasTenantScope, SoftDeletes;

    protected $fillable = [
        'user_id',
        'platform',
        'label',
        'province_id',
        'city_id',
        'district',
        'postal_code',
        'address_line', 'plate', 'unit',
        'receiver_name',
        'receiver_phone',
        'receiver_national_code',
        'latitude',
        'longitude',
        'is_default',
        'is_billing',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'is_default' => 'boolean',
            'is_billing' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Legacy addresses (WordPress import) carry a city but no province. Derive
        // it on every write so province_id is never a hole for reporting or for
        // province-scoped shipping rates.
        static::saving(function (self $address): void {
            if ($address->province_id !== null || $address->city_id === null) {
                return;
            }

            $address->province_id = $address->city?->province_id;
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    public function scopeBilling($query)
    {
        return $query->where('is_billing', true);
    }

    public function getFullAddressAttribute(): string
    {
        $parts = [
            $this->province?->name,
            $this->city?->name,
            $this->district,
            $this->address_line,
        ];

        if ($this->plate) {
            $unitPart = $this->unit ? ' واحد '.$this->unit : '';
            $parts[] = 'پلاک '.$this->plate.$unitPart;
        }

        $address = implode('، ', array_filter($parts));

        if ($this->postal_code) {
            $address .= ' - کد پستی: '.$this->postal_code;
        }

        return $address;
    }
}
