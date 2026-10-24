<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DiscountCode extends Model
{
    protected $fillable = [
        'code',
        'discount_amount',
        'platforms',
        'is_active',
        'starts_at',
        'ends_at',
        'max_uses',
        'max_uses_per_customer',
    ];

    protected function casts(): array
    {
        return [
            'platforms' => 'array',
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function usages(): HasMany
    {
        return $this->hasMany(DiscountCodeUsage::class);
    }
}
