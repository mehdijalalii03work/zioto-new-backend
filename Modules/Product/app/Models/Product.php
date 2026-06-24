<?php

namespace Modules\Product\Models;

use App\Enums\Product\Ayar;
use App\Enums\Product\MetalType;
use App\Enums\Product\ProductShape;
use App\Models\Brand;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Cache;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'brand_id',
        'name',
        'slug',
        'sku',
        'price_type',
        'description',
        'metal_type',
        'form',
        'ayar',
        'weight',
        'price_board_item',
        'fee_off_hours',
        'fee_business_hours',
        'price',
        'stock_quantity',
        'sort_order',
        'hesabfa_physical_stock',
        'hesabfa_reserved_stock',
        'hesabfa_manual_reserved',
        'hesabfa_exclude_from_sync',
        'hesabfa_stock_locked',
        'hesabfa_stock_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'metal_type' => MetalType::class,
            'form' => ProductShape::class,
            'ayar' => Ayar::class,
            'weight' => 'decimal:2',
            'fee_off_hours' => 'decimal:2',
            'fee_business_hours' => 'decimal:2',
            'price' => 'decimal:2',
            'stock_quantity' => 'integer',
            'sort_order' => 'integer',
            'hesabfa_physical_stock' => 'decimal:2',
            'hesabfa_reserved_stock' => 'decimal:2',
            'hesabfa_manual_reserved' => 'decimal:2',
            'hesabfa_exclude_from_sync' => 'boolean',
            'hesabfa_stock_locked' => 'boolean',
            'hesabfa_stock_synced_at' => 'datetime',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class);
    }

    public function primaryImage(): HasOne
    {
        return $this->hasOne(ProductImage::class)->where('is_primary', true);
    }

    public function getSellableStockAttribute(): int
    {
        $physical = (int) ($this->hesabfa_physical_stock ?? $this->stock_quantity ?? 0);
        $reserved = (int) ($this->hesabfa_reserved_stock ?? 0);
        $manualReserved = (int) ($this->hesabfa_manual_reserved ?? 0);

        return max(0, $physical - $reserved - $manualReserved);
    }

    public function calculatePrice(): ?float
    {
        if ($this->price_type !== 'dynamic') {
            return null;
        }

        if (! $this->price_board_item || ! $this->weight) {
            return null;
        }

        $prices = Cache::get('priceboard:prices', []);
        $products = $prices['products'] ?? [];

        $boardItem = null;
        foreach ($products as $item) {
            if (($item['name'] ?? '') === $this->price_board_item) {
                $boardItem = $item;
                break;
            }
        }

        if (! $boardItem || ! isset($boardItem['sellPrice'])) {
            return null;
        }

        $sellPrice = (float) $boardItem['sellPrice'];
        $weightInGrams = (float) $this->weight;

        $hour = (int) now()->format('H');
        $minute = (int) now()->format('i');
        $currentTime = $hour * 60 + $minute;

        $offHoursStart = 18 * 60;
        $offHoursEnd = 8 * 60 + 59;

        $isOffHours = $currentTime >= $offHoursStart || $currentTime <= $offHoursEnd;
        $fee = $isOffHours ? (float) $this->fee_off_hours : (float) $this->fee_business_hours;

        return round($weightInGrams * $sellPrice * (1 + $fee / 100));
    }
}
