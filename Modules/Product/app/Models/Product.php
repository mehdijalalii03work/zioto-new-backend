<?php

namespace Modules\Product\Models;

use App\Enums\Product\Ayar;
use App\Enums\Product\MetalType;
use App\Enums\Product\ProductShape;
use App\Models\Brand;
use App\Services\Pricing\DynamicPriceService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Product extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    protected $attributes = [
        'is_nopay' => false,
    ];

    protected $fillable = [
        'category_id',
        'brand_id',
        'name',
        'slug',
        'sku',
        'status',
        'visibility',
        'contact_only',
        'tokeniko_sku',
        'tapsi_product_id',
        'price_type',
        'description',
        'metal_type',
        'form',
        'ayar',
        'weight',
        'price_board_item',
        'fee_off_hours',
        'fee_business_hours',
        'labor_coefficients',
        'price',
        'stock_quantity',
        'sort_order',
        'hesabfa_physical_stock',
        'hesabfa_reserved_stock',
        'hesabfa_manual_reserved',
        'hesabfa_exclude_from_sync',
        'hesabfa_stock_locked',
        'hesabfa_stock_synced_at',
        'is_nopay',
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
            'contact_only' => 'boolean',
            'is_nopay' => 'boolean',
            'labor_coefficients' => 'array',
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

        return $this->sellableStockFor($physical);
    }

    /**
     * Single source of truth for "how much can actually be sold".
     *
     * `hesabfa_reserved_stock` keeps being written by the order lifecycle
     * (see StockReservationObserver) no matter what, but when the
     * `ignore_reserved_stock` setting is on it is left out of the calculation
     * so only the stock coming from Hesabfa decides availability.
     * `hesabfa_manual_reserved` is an admin decision and always applies.
     */
    public function sellableStockFor(int $physicalStock): int
    {
        $reserved = static::ignoresReservedStock() ? 0 : (int) ($this->hesabfa_reserved_stock ?? 0);
        $manualReserved = (int) ($this->hesabfa_manual_reserved ?? 0);

        return max(0, $physicalStock - $reserved - $manualReserved);
    }

    public static function ignoresReservedStock(): bool
    {
        return (bool) setting('ignore_reserved_stock', true);
    }

    /**
     * Persisted base price for this product (basic labor role).
     * Used by sync and non-session consumers (Rige, Tapsi, admin display).
     */
    public function calculatePrice(): ?float
    {
        if ($this->price_type !== 'dynamic') {
            return null;
        }

        if (! $this->price_board_item || ! $this->weight) {
            return null;
        }

        return app(DynamicPriceService::class)->basePriceFor($this);
    }

    /**
     * Live price for a specific user (labor role + time period aware).
     */
    public function priceForUser(?int $userId = null): float
    {
        $dynamic = app(DynamicPriceService::class)->priceFor($this, $userId);

        return (float) ($dynamic ?? $this->price);
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    public function scopePublicVisible($query)
    {
        return $query->where('visibility', 'public');
    }

    public function scopePubliclyListed($query)
    {
        return $query->published()->publicVisible();
    }

    public function scopeNopayEligible($query)
    {
        return $query->where('is_nopay', true);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('product-images')
            ->useDisk('public');
    }
}
