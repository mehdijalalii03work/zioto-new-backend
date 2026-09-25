<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiscountCode extends Model
{
    public const TYPES = [
        'labor_percent' => 'درصد تخفیف روی اجرت',
        'total_percent' => 'درصد تخفیف روی کل مبلغ',
        'fixed_amount' => 'مبلغ ثابت',
    ];

    protected $fillable = [
        'code',
        'type',
        'value',
        'is_active',
        'is_user_specific',
        'allowed_users',
        'usage_limit',
        'usage_count',
        'expiry_date',
        'min_order_amount',
        'allowed_metal_types',
        'allowed_labor_roles',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'float',
            'min_order_amount' => 'float',
            'is_active' => 'boolean',
            'is_user_specific' => 'boolean',
            'allowed_users' => 'array',
            'allowed_metal_types' => 'array',
            'allowed_labor_roles' => 'array',
            'usage_limit' => 'integer',
            'usage_count' => 'integer',
            'expiry_date' => 'date',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function isExpired(): bool
    {
        return $this->expiry_date !== null && $this->expiry_date->isPast();
    }

    public function isUsable(): bool
    {
        if (! $this->is_active || $this->isExpired()) {
            return false;
        }

        if ($this->usage_limit > 0 && $this->usage_count >= $this->usage_limit) {
            return false;
        }

        return true;
    }
}
