# گزارش تغییرات اصلاحی یکپارچه‌سازی حسابفا

> تاریخ: ۲۷ جولای ۲۰۲۶  
> مبنای تحلیل: پلاگین وردپرسی `zioto-hesabfa-integration` نسخه ۱.۰.۵ (پایدار)

---

## فهرست تغییرات

| ردیف | فایل | نوع تغییر | توضیح |
|------|------|-----------|--------|
| ۱ | `app/Observers/StockReservationObserver.php` | **اصلاح** | اضافه شدن `isEnabled()`، Transaction، guard روی decrement |
| ۲ | `app/Console/Commands/RecalculateReservedStock.php` | **جدید** | دستور `hesabfa:recalculate-reserved` برای بازکالیبراسیون |
| ۳ | `app/Observers/HesabfaObserver.php` | **اصلاح** | مالیات نقره، DB transaction، کش SKU، validation پیش از شروع، لاگ موفقیت |
| ۴ | `app/Services/StockSyncService.php` | **اصلاح** | بررسی `hesabfa_exclude_from_sync`، sellable stock، لاگ موفقیت |
| ۵ | `app/Http/Controllers/Api/HesabfaWebhookController.php` | **اصلاح** | حذف `$request->query('secret')` |
| ۶ | `routes/api.php` | **اصلاح** | افزودن `throttle:60,1` به مسیر webhook |
| ۷ | `app/Providers/AppServiceProvider.php` | **اصلاح** | `withoutOverlapping()` روی cron + اضافه شدن nightly recalculate |
| ۸ | `app/Http/Controllers/Api/PaymentController.php` | **اصلاح** | اعتبارسنجی موجودی قبل از تغییر وضعیت به confirmed |
| ۹ | `Modules/Product/app/Models/Product.php` | **اصلاح** | اضافه شدن `hesabfa_manual_reserved_history` به casts |
| ۱۰ | `config/hesabfa.php` | **اصلاح** | اضافه شدن کلید `price_unit` |

---

## جزئیات فنی تغییرات

### ۱. StockReservationObserver.php

**مشکلات قبل:**
- `enable_reserved_stock` هیچ‌جا بررسی نمی‌شد
- `decrement` بدون guard → احتمال منفی شدن `hesabfa_reserved_stock`
- رزرو چند محصول در یک سفارش بدون transaction → inconsistency در صورت fail

**تغییرات اعمال شده:**

```php
// بررسی فعال بودن سیستم رزرو
private function isEnabled(): bool
{
    return config('hesabfa.enable_reserved_stock', false);
}
```

این متد در `created()`، `updated()` و `deleted()` صدا زده می‌شود. اگر `enable_reserved_stock` برابر `false` باشد، هیچ عملیاتی انجام نمی‌شود.

```php
// محافظت از decrement
DB::table('products')
    ->where('id', $productId)
    ->where('hesabfa_reserved_stock', '>=', $quantity)
    ->decrement('hesabfa_reserved_stock', $quantity);
```

```php
// Transaction در reserveStock
DB::transaction(function () use ($order, $reservations) {
    // ...
});
```

### ۲. RecalculateReservedStock (دستور جدید)

**هدف:** بازکالیبراسیون خودکار `hesabfa_reserved_stock` از روی سفارش‌های فعال با وضعیت `confirmed`.

```bash
php artisan hesabfa:recalculate-reserved
```

منطق کار:
1. جمع‌زنی `quantity` از `order_items` برای همه سفارش‌های `confirmed`
2. مقایسه با مقدار فعلی `hesabfa_reserved_stock`
3. بروزرسانی فقط محصولاتی که اختلاف دارند
4. استفاده از `updateQuietly()` برای جلوگیری از فعال شدن Observerها

زمان‌بندی: هر شب ساعت ۳ صبح (`->dailyAt('03:00')`)

### ۳. HesabfaObserver.php (مالیات نقره + تراکنش)

**مشکل قبل:** `tax => 0` هاردکد شده برای همه محصولات. نقره نیاز به ۱۰٪ مالیات دارد.

**تغییرات:**

**۳.۱. محاسبه مالیات بر اساس نوع فلز:**
```php
$metalType = $product?->metal_type;
$priceBoardItem = $product?->price_board_item ?? '';
$isSilver = $metalType === MetalType::Silver
    || str_starts_with($priceBoardItem, 'Silver');

if ($isSilver) {
    $taxRate = (float) Setting::getValue('tax_silver', 10);
    if ($taxRate > 0) {
        $unitPrice = (int) round($price / (1 + $taxRate / 100));
        $tax = $price - $unitPrice;
    }
}
```

**۳.۲. کش SKU درون‌حافظه‌ای:**
```php
$skuCache = [];
if (! isset($skuCache[$sku])) {
    $skuCache[$sku] = $this->findHesabfaItemCode($sku);
}
```
جلوگیری از تماس‌های تکراری API برای محصولات تکراری.

**۳.۳. جمع‌آوری SKUهای گمشده (مثل WP):**
```php
if (! $itemCode) {
    $missingSkus[] = $sku;
    continue;
}
// ...
if (! empty($missingSkus)) {
    return false;
}
```

**۳.۴. DB Transaction در syncOrder:**
```php
DB::beginTransaction();
// ... contact sync ...
// ... invoice sync ...
DB::commit();
// در صورت خطا:
DB::rollBack();
```

**۳.۵. لاگ موفقیت:**
```php
$this->log($order, 'full_sync', 'success', [
    'contact_code' => $contactResult['contact_code'],
    'invoice_number' => $invoiceResult['invoice_number'],
    'reference' => $invoiceResult['reference'],
]);
```

**۳.۶. اعتبارسنجی کد کالاها قبل از شروع:**
متد `preValidateInvoiceItems()` بررسی می‌کند که `shipping_item_code` و `installment_fee_item_code` در حسابفا وجود داشته باشند قبل از شروع sync.

### ۴. StockSyncService.php

**۴.۱. بررسی `hesabfa_exclude_from_sync`:**
```php
if ($product->hesabfa_stock_locked || $product->hesabfa_exclude_from_sync) {
    continue;
}
```

**۴.۲. محاسبه `stock_quantity` با احتساب رزرو:**
```php
if ($reservedEnabled) {
    $reserved = (int) ($product->hesabfa_reserved_stock ?? 0);
    $manualReserved = (int) ($product->hesabfa_manual_reserved ?? 0);
    $updateData['stock_quantity'] = max(0, $quantity - $reserved - $manualReserved);
} else {
    $updateData['stock_quantity'] = $quantity;
}
```

**۴.۳. لاگ موفقیت در دیتابیس:**
```php
HesabfaSyncLog::create([
    'sync_type' => 'stock_sync',
    'status' => 'success',
    'response_data' => ['updated_count' => $updated, 'errors' => $errors],
]);
```

**۴.۴. وب‌هوک قیمت با تنظیم واحد:**
استفاده از `config('hesabfa.price_unit', 'rial')` به جای hardcoded تقسیم بر ۱۰.

**۴.۵. `updateStockByItemCode` + `updatePriceByItemCode`**: بررسی `hesabfa_exclude_from_sync` اضافه شد.

### ۵. HesabfaWebhookController.php

حذف `$request->query('secret')` از خط ۳۳:
```php
// قبل:
$providedSecret = $request->header('X-Webhook-Secret')
    ?? $request->input('secret')
    ?? $request->query('secret');

// بعد:
$providedSecret = $request->header('X-Webhook-Secret')
    ?? $request->input('secret');
```

### ۶. routes/api.php

افزودن `->middleware('throttle:60,1')` به مسیر webhook:
```php
Route::post('/webhook', [HesabfaWebhookController::class, 'handle'])
    ->name('hesabfa.webhook')
    ->middleware('throttle:60,1');
```

### ۷. AppServiceProvider.php

```php
Schedule::command('hesabfa:sync-stock')
    ->cron("*/{$interval} * * * *")
    ->withoutOverlapping();

Schedule::command('hesabfa:recalculate-reserved')
    ->dailyAt('03:00')
    ->withoutOverlapping();
```

### ۸. PaymentController.php (اعتبارسنجی موجودی)

متد جدید `validateOrderStock()` که قبل از تغییر وضعیت به `confirmed` صدا زده می‌شود:

```php
private function validateOrderStock(Order $order): array
{
    $reservedEnabled = config('hesabfa.enable_reserved_stock', false);
    if (! $reservedEnabled) {
        return ['valid' => true];
    }

    $order->load('items.product');
    $insufficient = [];

    foreach ($order->items as $item) {
        $product = $item->product;
        $physical = (int) ($product->hesabfa_physical_stock ?? $product->stock_quantity ?? 0);
        $reserved = (int) ($product->hesabfa_reserved_stock ?? 0);
        $manualReserved = (int) ($product->hesabfa_manual_reserved ?? 0);
        $sellable = max(0, $physical - $reserved - $manualReserved);

        if ($item->quantity > $sellable) {
            $insufficient[] = "{$product->name}: موجودی {$sellable}، درخواست {$item->quantity}";
        }
    }

    if (! empty($insufficient)) {
        return ['valid' => false, 'message' => 'موجودی کافی نیست', 'product' => implode(', ', $insufficient)];
    }

    return ['valid' => true];
}
```

### ۹. Product.php

اضافه شدن `hesabfa_manual_reserved_history` به `casts()` برای ذخیره تاریخچه تغییرات رزرو دستی.

### ۱۰. config/hesabfa.php

اضافه شدن:
```php
'price_unit' => env('HESABFA_PRICE_UNIT', 'rial'),
```

---

## مقایسه قبل و بعد (WP → Laravel)

| قابلیت | وردپرس (پایدار) | لاراول قبل | لاراول بعد |
|--------|-----------------|-------------|------------|
| فعال/غیرفعال رزرو | ✅ | ❌ | ✅ |
| Transaction در sync | ✅ | ❌ | ✅ |
| لاگ موفقیت | ✅ | ❌ | ✅ |
| کش SKU | ✅ | ❌ | ✅ |
| مالیات نقره (۱۰٪) | ✅ | ❌ (`tax=0`) | ✅ |
| اعتبارسنجی کد کالاها قبل از sync | ✅ | ❌ | ✅ |
| بررسی `exclude_from_sync` | ✅ | ❌ | ✅ |
| `stock_quantity` = sellable | ✅ | ❌ | ✅ |
| Guard روی decrement | ✅ | ❌ | ✅ |
| Cron بدون overlap | ❌ (WP Cron) | ❌ | ✅ |
| اعتبارسنجی سبد خرید | ✅ | ❌ | ✅ |
| بازکالیبراسیون خودکار | ❌ (نیاز نبود) | ❌ | ✅ (شبانه) |
| Webhook بدون secret در query | ✅ | ❌ | ✅ |
| Rate limiting روی webhook | ✅ (WP) | ❌ | ✅ |

---

## دستورات جدید Artisan

```bash
# بازکالیبراسیون موجودی رزرو شده
php artisan hesabfa:recalculate-reserved
```

---

## زمان‌بندی Cron (جدید)

| Command | Cron | توضیح |
|---------|------|-------|
| `hesabfa:sync-stock` | `*/{interval} * * * *` | همگام‌سازی موجودی (قبلی + `withoutOverlapping`) |
| `hesabfa:recalculate-reserved` | `0 3 * * *` | **جدید:** بازکالیبراسیون شبانه رزرو |
