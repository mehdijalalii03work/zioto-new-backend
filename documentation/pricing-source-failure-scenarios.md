# سناریوهای قطعی منابع قیمت (Tala.ir / PersianAPI)

بررسی رفتار تابلو قیمت و قیمت محصولات سایت وقتی یکی یا هر دو منبعِ قیمت از کار می‌افتند.

---

## زنجیرهٔ پردازش (مبنای همهٔ سناریوها)

```
fetchFromApi() → [منبع سالم؟ استفاده : raw fallback از settings]
      → buildPrices() → کش (TTL 300s) + settings
      → قیمت هر دقیقه توسط priceboard:sync بازمحاسبه می‌شود → products.price
```

کلید مسئول: `maybeUseRawFallback()` در `app/Services/Pricing/ApiClientService.php:592` — اگر fetch زنده خالی بود، **آخرین دادهٔ موفق همان منبع** را از تنظیمات (`last_successful_raw_data`) برمی‌گرداند و status را `fallback` می‌گذارد.

---

## سناریو ۱: فقط یکی از منابع down (مثلاً Tala)

- منبع خراب → `fallback` (دادهٔ قدیمی خودش) یا در بدترین حالت `down`.
- منبع سالم → زنده. `buildPrices()` با `max/min` روی هر دو کار می‌کند و حتی تک‌منبعی هم جواب می‌دهد (`ApiClientService.php:229-263`).
- **تابلو:** عملاً سالم است، فقط دادهٔ منبعِ خراب یخ‌زده است. در داشبورد ادمین بج «ذخیره‌شده» (کهربایی) کنار آن منبع می‌افتد (`pricing-dashboard.blade.php:26`) و در API هم `source_status` برگردانده می‌شود (`PriceBoardController.php:18`).
- **قیمت محصولات:** sync هر دقیقه board را re-derive و قیمت‌ها را re-حساب می‌کند → فقط نوسان واقعیِ منبع سالم اعمال می‌شود. سایت کاملاً عادی است.
- **ریزه‌کاری:** ترکیب «منبع زنده + منبع قدیمی» می‌تواند موقتاً ناهماهنگ شود (مثلاً `max(Persian زنده، Tala سه‌ساعته)`). همچنین Silver9999 فقط از PersianAPI می‌آید (`ApiClientService.php:283-299`) ⇒ اگر Persian خراب و fallback هم نداشته باشد، نقرهٔ تابلو حذف می‌شود (مگر قیمت دستی set شده باشد).

---

## سناریو ۲: هر دو منبع همزمان down (حالت عادیِ پیش‌فرض)

**تابلو = منجمد (freeze)، نه خراب.**

1. هر دو به raw fallback می‌روند → board از **آخرین دادهٔ موفق** بازسازی می‌شود (`used_fallback=true`).
2. اگر raw هم نبود → `getFallbackPrices()` کل آرایهٔ `last_successful_data` را برمی‌گرداند (`ApiClientService.php:194-199, 627`) → باز هم منجمد.
3. trendها همگی `stable` می‌شوند (چون قیمت‌ها تغییر نکرده‌اند).
4. **قیمت محصولات:** sync با board منجمد بازمحاسبه می‌کند ⇒ عملاً بدون تغییر → broadcast و push به Tapsi با همان قیمت‌های قبلی.
5. **کاربر سایت:** `priceForUser()` همچنان از board منجمد قیمت می‌گیرد (`DynamicPriceService.php:62-65`) — **هیچ نشانهٔ خرابی نمی‌بیند**.

> ⚠️ چون `stale_block` پیش‌فرض `false` است (`PricingSettings.php:95`)، این انجماد **برای همیشه** ادامه دارد — حتی بعد از چند روز، بدون هیچ خطای صریح. فقط بج کهربایی در پنل ادمین.

---

## سناریو ۳: fallback هم موجود نیست (یا `stale_block=true` بعد از ۶ ساعت)

- `buildPrices()=[]` → لاگ خطا: `'[ZiotoPricing] No price data after fetching sources.'` (`ApiClientService.php:195`)
- `priceboard:sync` می‌بیند `empty($prices)` → **FAILURE و خروج زودهنگام** (`SyncPriceBoard.php:39-43`) ⇒
  - ❌ قیمت محصولات re-محاسبه **نمی‌شود** → `products.price` روی آخرین مقدار ذخیره‌شده می‌ماند
  - ❌ broadcast و push به Tapsi انجام **نمی‌شود**
- سایت از کار نمی‌افتد: `priceFor()` وقتی board خالی `null` برمی‌گرداند و `priceForUser()` به `products.price` برمی‌گردد (`Product.php:136`) — یعنی همان آخرین قیمت معتبر نمایش داده می‌شود.

---

## جمع‌بندی ریسک‌ها

| وضعیت | تابلو | قیمت محصولات سایت | قابلیت تشخیص |
|---|---|---|---|
| یک منبع down | سالم + ناهماهنگی جزئی | عادی | بج «ذخیره‌شده» در پنل ادمین |
| هر دو down (پیش‌فرض) | **منجمد** روی آخرین قیمت | **منجمد** | فقط بج کهربایی؛ کاربر عادی هیچ نشانه‌ای نمی‌بیند |
| down + بدون fallback / stale_block | خالی | ثابت روی `products.price` | لاگ خطا + sync FAILURE |

**نکات ریز:**

1. **ریسک مالی واقعی:** اگر بازار طلا بُکشد و منابع down باشند، سایت زیر قیمت بازار می‌فروشد (یا برعکس موجودی را گران). برای طلافروشی این مهم‌ترین خطر است.
2. **`priceboard:last_sync_at` بدون شرط ست می‌شود** (`ApiClientService.php:88`) ⇒ چک `fromApi` در sync (`SyncPriceBoard.php:48`) همیشه `true` است و پیامِ `Using cached prices (API unavailable)` عملاً هرگز چاپ نمی‌شود — یعنی گزارش کرون نمی‌فهمد fallback بوده. برای مانیتورینگ باید از `source_status`/`used_fallback` استفاده کرد نه `last_sync_at`.
3. **تاریخچهٔ قیمت** هم رکوردهای fallback را مثل دادهٔ سالم می‌نویسد (`PriceHistoryService.php:20-25`) — در نمودار تشخیص دورهٔ قطعی سخت می‌شود.
4. راه‌حل پیشنهادی: فعال کردن `stale_block` + مانیتورینگ روی `source_status` (یا alert وقتی `used_fallback=true` بیش از X دقیقه بماند) تا انجمادِ بی‌صدا به قطعیِ آشکار تبدیل شود.

---

## فایل‌های کلیدی

- `app/Services/Pricing/ApiClientService.php` — fetch، fallback، ساخت تابلو
- `app/Services/Pricing/PricingSettings.php` — `stale_block` / `stale_max_age_seconds` / داده‌های last-successful
- `app/Console/Commands/Tokeniko/SyncPriceBoard.php` — کرون هر دقیقه، بازمحاسبهٔ قیمت محصولات
- `app/Services/Pricing/DynamicPriceService.php` — قیمت لحظه‌ای برای کاربر
- `Modules/Product/app/Models/Product.php` — `calculatePrice()` / `priceForUser()`
- `app/Http/Controllers/Api/PriceBoardController.php` — خروجی API تابلو + `source_status`
- `resources/views/filament/pages/pricing/pricing-dashboard.blade.php` — بج‌های وضعیت منابع
