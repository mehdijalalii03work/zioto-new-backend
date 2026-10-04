# دکمه‌های `/admin/pricing/dashboard`

سه دکمهٔ بالای داشبورد قیمت، همگی از طریق Livewire به متدهای `PricingDashboard` وصل هستند:

| دکمه | متد | فایل | مجوز لازم |
|---|---|---|---|
| بروزرسانی نمایش | `refreshBoard()` | `app/Filament/Pages/Pricing/PricingDashboard.php:86` | فقط `pricing.view` |
| بروزرسانی از API | `forceRefresh()` | `app/Filament/Pages/Pricing/PricingDashboard.php:91` | `pricing.edit` |
| پاک کردن کش | `clearCache()` | `app/Filament/Pages/Pricing/PricingDashboard.php:104` | `pricing.edit` |

---

## ۱. بروزرسانی نمایش (`refreshBoard`)

فقط **خواندنی** است — کش برد را دوباره می‌خواند و محاسبات/فرمول نمایش را در حافظه از نو حساب می‌کند.

- هیچ نوشتنی روی کش یا دیتابیس ندارد.
- مگر کش خالی/منقضی باشد، **هیچ درخواست خارجی نمی‌زند**.
- نیازی به `pricing.edit` ندارد (هر کاربرِ دارای `pricing.view` می‌تواند بزند).

**کی استفاده کنم:** وقتی فکر می‌کنم صفحه عقب‌تر از دادهٔ کش است یا می‌خواهم نمایش را بدون هزینهٔ API دوباره رسم کنم.

---

## ۲. بروزرسانی از API (`forceRefresh`)

نیازمند مجوز `pricing.edit`.

1. ۷ کلید کش برد را پاک می‌کند:
   `zioto:payload`، `zioto:prices`، `zioto:debug`، `zioto:source_status`، `zioto:raw`، `priceboard:prices`، `priceboard:last_sync_at`
2. با `forceRefresh: true` مستقیماً از **PersianAPI** و **Tala.ir** داده می‌گیرد (هر کدام timeout ۱۵ ثانیه، ۲ تلاش مجدد ⇒ در بدترین حالت چندین ثانیه طول می‌کشد).
3. برد را با ضرایب (`coef_*`) و قیمت‌های دستی بازسازی می‌کند.
4. کش (TTL پیش‌فرض ۳۰۰ ثانیه) و تنظیمات `previous_prices`، `last_successful_*`، `last_raw_price_changes` را می‌نویسد.

**کی استفاده کنم:** وقتی مطمئنم منبعِ خارجی تغییر کرده و به دادهٔ تازه نیاز دارم (مثلاً قیمت طلای بازار عوض شده و کش ۳۰۰ ثانیه‌ای هنوز دادهٔ قدیمی دارد).

---

## ۳. پاک کردن کش (`clearCache`)

نیازمند مجوز `pricing.edit`.

1. همان ۷ کلید کش برد را پاک می‌کند (دقیقاً همان‌هایی که `forceRefresh` پاک می‌کند).
2. بلافاصله `refreshBoard()` را صدا می‌زند؛ چون کش خالی است، `getPrices()` به `getAllPrices()` می‌افتد و عملاً **خودش هم به APIهای خارجی دست می‌زند** و کش را دوباره پر می‌کند.

**کی استفاده کنم:** وقتی مشکوکم کش خراب/ناهماهنگ شده (مثلاً بعد از تغییر تنظیمات یا دستکاری) و می‌خواهم از صفر بازسازی شود.

**پاک نمی‌کند:** کش‌های `setting:*` (ضرایب، قیمت‌های دستی، داده‌های last-successful)، کش فروشگاه `api:products:*`، یا هر کش غیر از برد.

---

## جمع‌بندی سریع

| | هزینه | درخواست خارجی | می‌نویسد | مجوز |
|---|---|---|---|---|
| بروزرسانی نمایش | تقریباً رایگان | فقط اگر کش خالی/منقضی باشد | نه | `pricing.view` |
| بروزرسانی از API | بالا (تا ۲ HTTP) | بله، همیشه | کش + settings | `pricing.edit` |
| پاک کردن کش | بالا (تا ۲ HTTP) | بله (بعد از پاک کردن) | کش + settings | `pricing.edit` |

> **نکته:** دکمه‌های ۲ و ۳ عملاً تقریباً یکسان‌اند — هر دو کش را می‌پاکند و API می‌زنند. تفاوت فقط در قصد است: «۲» صریح force است، «۳» اول پاک می‌کند بعد بازسازی.

---

## نکات مهم

- **هیچ‌کدام قیمت محصولات (`products.price`) را به‌روزرسانی نمی‌کنند** و رویداد `PriceBoardUpdated`/`ProductsUpdated` هم پخش نمی‌کنند. این کارها فقط با کرونِ هر-دقیقه‌ای `priceboard:sync` انجام می‌شود (`routes/console.php:5-9`، پیاده‌سازی در `app/Console/Commands/Tokeniko/SyncPriceBoard.php`).
  ⇒ اگر قیمت محصولات فروشگاه باید عوض شود، باید تا اجرای بعدی sync صبر کنید یا دستی آن را اجرا کنید.
- کش برد با TTL تنظیم `zioto_pricing_cache_duration_seconds` (پیش‌فرض ۳۰۰، حداقل ۱۰ ثانیه) نگهداری می‌شود — `app/Services/Pricing/PricingSettings.php`.
- مسیر API مرتبط: `POST /api/.../price-board/refresh` (throttle `10,1`) همان `PriceBoardService::refresh()` را صدا می‌زند — `routes/api.php:87-88`.

## فایل‌های کلیدی

- `app/Filament/Pages/Pricing/PricingDashboard.php` — متدهای صفحه
- `resources/views/filament/pages/pricing/pricing-dashboard.blade.php` — دکمه‌ها (خطوط ۱۰۳–۱۱۲)
- `app/Services/PriceBoardService.php` — `getPrices()` / `refresh()` / `clearCache()`
- `app/Services/Pricing/ApiClientService.php` — خواندن کش، fetch از API، بازسازی برد
- `app/Services/Pricing/PricingSettings.php` — تنظیمات و کش‌های مرتبط
