<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Order\Models\OrderShipping;

class ShippingMethod extends Model
{
    use HasFactory;

    public $incrementing = false;

    protected $keyType = 'int';

    protected $fillable = [
        'id', 'code', 'name', 'description', 'icon',
        'is_active', 'is_pickup', 'sort_order', 'exclude_cities',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_pickup' => 'boolean',
            'sort_order' => 'integer',
            'exclude_cities' => 'array',
        ];
    }

    public function rates(): HasMany
    {
        return $this->hasMany(ShippingRate::class);
    }

    public function orderShippings(): HasMany
    {
        return $this->hasMany(OrderShipping::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }
}
