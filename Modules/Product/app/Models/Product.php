<?php

namespace Modules\Product\Models;

use App\Enums\Inventory\InventoryMode;
use App\Enums\Inventory\SerialStatus;
use App\Enums\Inventory\StockReservationStatus;
use App\Enums\Product\Ayar;
use App\Enums\Product\MetalType;
use App\Enums\Product\ProductShape;
use App\Models\Brand;
use App\Models\ProductSerial;
use App\Models\ProductStockReservation;
use App\Models\Setting;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Cache;
use Modules\Product\Database\Factories\ProductFactory;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Product extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    protected $attributes = [
        'is_nopay' => false,
    ];

    protected static function newFactory(): ProductFactory
    {
        return ProductFactory::new();
    }

    protected static function booted(): void
    {
        static::saving(function (Product $product) {
            if (is_string($product->serial_prefix)) {
                $normalized = strtolower(trim($product->serial_prefix));
                $product->serial_prefix = $normalized === '' ? null : $normalized;
            }
        });
    }

    protected $fillable = [
        'category_id',
        'brand_id',
        'name',
        'slug',
        'sku',
        'serial_prefix',
        'inventory_mode',
        'track_serials',
        'made_to_order',
        'production_lead_time_text',
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
            'inventory_mode' => InventoryMode::class,
            'track_serials' => 'boolean',
            'made_to_order' => 'boolean',
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

    public function serials(): HasMany
    {
        return $this->hasMany(ProductSerial::class);
    }

    public function manualReservations(): HasMany
    {
        return $this->hasMany(ProductStockReservation::class);
    }

    /**
     * Free serial bars. Reads the withCount aggregate when the product was
     * loaded with one (listings) instead of querying per product.
     */
    public function availableSerialCount(): int
    {
        if (array_key_exists('available_serial_count', $this->attributes)) {
            return (int) $this->attributes['available_serial_count'];
        }

        return (int) $this->serials()->where('status', SerialStatus::Available->value)->count();
    }

    /**
     * Active manual capacity holds. Reads the withSum aggregate when loaded.
     */
    public function activeManualReserved(): int
    {
        if (array_key_exists('active_manual_reserved', $this->attributes)) {
            return (int) $this->attributes['active_manual_reserved'];
        }

        return (int) $this->manualReservations()
            ->where('status', StockReservationStatus::Reserved->value)
            ->sum('quantity');
    }

    /**
     * Manual sellable capacity (stock minus active holds, never negative).
     */
    public function acceptableManualCount(): int
    {
        return max(0, (int) ($this->stock_quantity ?? 0) - $this->activeManualReserved());
    }

    public function isInStock(): bool
    {
        return $this->sellable_stock > 0;
    }

    public function getSellableStockAttribute(): int
    {
        // Serial products live and die by free bars; the numeric columns
        // (stock_quantity, hesabfa_*) are history only and never decide.
        if (($this->inventory_mode ?? InventoryMode::Manual) === InventoryMode::Serial) {
            return $this->availableSerialCount();
        }

        return $this->acceptableManualCount();
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

        $basePrice = $weightInGrams * $sellPrice * (1 + $fee / 100);

        $taxKey = str_starts_with($this->price_board_item, 'Gold') ? 'tax_gold' : 'tax_silver';
        $taxPercentage = (float) Setting::getValue($taxKey, 0);

        return round($basePrice * (1 + $taxPercentage / 100));
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
