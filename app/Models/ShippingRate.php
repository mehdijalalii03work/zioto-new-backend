<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShippingRate extends Model
{
    protected $fillable = [
        'shipping_method_id', 'rate_type', 'province_id', 'city_id',
        'min_weight', 'max_weight', 'min_cart_total', 'max_cart_total',
        'base_rate', 'per_kg_rate', 'free_shipping_min', 'tax_rate',
        'estimated_days_min', 'estimated_days_max',
    ];

    protected function casts(): array
    {
        return [
            'min_weight' => 'decimal:3',
            'max_weight' => 'decimal:3',
            'min_cart_total' => 'decimal:0',
            'max_cart_total' => 'decimal:0',
            'base_rate' => 'decimal:0',
            'per_kg_rate' => 'decimal:0',
            'free_shipping_min' => 'decimal:0',
        ];
    }

    public function shippingMethod(): BelongsTo
    {
        return $this->belongsTo(ShippingMethod::class);
    }

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function scopeForProvince($query, int $provinceId)
    {
        return $query->where('province_id', $provinceId);
    }

    public function scopeForCity($query, int $cityId)
    {
        return $query->where('city_id', $cityId);
    }
}
