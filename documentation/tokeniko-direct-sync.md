# مستندات: سیستم قیمت‌گذاری مستقیم توکنیکو + اتصال تپسی شاپ

## خلاصه

در این فاز، سیستم قیمت‌گذاری پروژه لاراولی رو از حالت تابلویی (dynamic) به حالتی رساندیم که بتونه **قیمت‌ها رو مستقیماً از API توکنیکو دریافت کنه** (مثل وردپرس) و به **تپسی شاپ** ارسال کنه. همچنین با یک متغیر env می‌تونید بین این دو حالت سوییچ کنید.

---

## معماری کلی

```
┌─────────────────────────────────────────────────┐
│              PRICING_MODE (env)                  │
├──────────────────────┬──────────────────────────┤
│   dynamic (تابلویی)  │     direct (مستقیم)      │
├──────────────────────┼──────────────────────────┤
│ PriceBoardService    │ TokenikoShopService      │
│ (tokeniko.com/api/   │ (apigateway.tokeniko.com │
│  prices-with-change) │  /shop/api/Category/     │
│                      │   getPrices)             │
├──────────────────────┼──────────────────────────┤
│ calculatePrice()     │ قیمت مستقیم از API       │
│ (وزن × قیمت تابلو   │ تقسیم بر 10 (ریال→تومان) │
│  × ضریب اجرت)       │                          │
├──────────────────────┴──────────────────────────┤
│              SyncPriceBoard Command              │
│          (هر ۱ دقیقه اجرا می‌شه)                │
└──────────────────────┬──────────────────────────┘
                       │
                       ▼
┌──────────────────────┴──────────────────────────┐
│              Product.price (دیتابیس)             │
└──────────────────────┬──────────────────────────┘
                       │
                       ▼
┌──────────────────────┴──────────────────────────┐
│         TAPSI_SYNC_ENABLED = true?              │
├──────────────────────┬──────────────────────────┤
│        ✅ بله         │        ❌ خیر            │
│                      │  (فقط آپدیت دیتابیس     │
│                      │   + broadcast)            │
│         ▼            │                          │
│  TapsiShopService    │                          │
│  PUT .../products    │                          │
│  + markup: زیر ۵۰M  │                          │
│    → ۲٪ / بالای ۵۰M │                          │
│    → ۱٪             │                          │
└──────────────────────┴──────────────────────────┘
```

### توضیح تفصیلی معماری

سیستم قیمت‌گذاری محصولات در سه لایه عمل می‌کنه:

**لایه اول — دریافت قیمت خام:**

بسته به متغیر `PRICING_MODE` در فایل `.env`، یکی از دو سرویس زیر فعال می‌شه:

- حالت `dynamic` → سرویس `PriceBoardService` از آدرس `tokeniko.com/api/prices-with-change` قیمت‌های تابلو (قیمت هر گرم طلا/نقره) رو دریافت می‌کنه. این قیمت‌ها خام هستن و هنوز ضریب اجرت و وزن اعمال نشده.

- حالت `direct` → سرویس `TokenikoShopService` از آدرس `apigateway.tokeniko.com/shop/api/Category/getPrices` قیمت نهایی هر محصول رو مستقیماً دریافت می‌کنه. این API عمومیه و نیاز به کلید یا توکن نداره. قیمت‌ها به ریال برمی‌گردن و با تقسیم بر ۱۰ به تومان تبدیل می‌شن.

هر دو سرویس از کش استفاده می‌کنن تا فشار به API‌ها کم بشه.

**لایه دوم — محاسبه و ذخیره قیمت:**

کامند `priceboard:sync` هر ۱ دقیقه توسط Scheduler اجرا می‌شه و بسته به حالت فعال:

- در حالت `dynamic`: فرمول `وزن_محصول × قیمت_تابلو × (۱ + درصد_اجرت)` روی هر محصول اعمال می‌شه. درصد اجرت بسته به ساعت روز (ساعت اداری یا غیراداری) متفاوته.

- در حالت `direct`: قیمت مستقیماً از API توکنیکو خونده شده و بدون هیچ فرمولی در دیتابیس ذخیره می‌شه.

در هر دو حالت، قیمت جدید در جدول `products` ذخیره می‌شه و رویداد `ProductsUpdated` از طریق WebSocket (Reverb) به فرانت‌اند broadcast می‌شه تا قیمت‌های سایت لحظه‌ای آپدیت بشن.

**لایه سوم — ارسال به تپسی شاپ:**

اگر متغیر `TAPSI_SYNC_ENABLED` برابر `true` باشه، قیمت‌های به‌روز شده به API تپسی شاپ ارسال می‌شن. قبل از ارسال، دو تغییر روی قیمت‌ها اعمال می‌شه:

1. **افزایش درصدی (markup):** اگر قیمت محصول کمتر از ۵۰ میلیون تومان باشه ۲٪ و اگر بیشتر باشه ۱٪ به قیمت اضافه می‌شه. این اختلاف قیمت بین سایت اصلی و تپسی شاپ هست.

2. **تبدیل واحد:** قیمت نهایی از تومان به ریال تبدیل می‌شه (ضرب در ۱۰) چون API تپسی شاپ با ریال کار می‌کنه.

در کنار قیمت، موجودی قابل فروش محصول هم ارسال می‌شه. اگر کلید اضطراری (Kill Switch) از پنل فیلمنت فعال باشه، موجودی صفر ارسال می‌شه تا فروش در تپسی متوقف بشه بدون اینکه قیمت‌ها یا موجودی واقعی سایت تغییر کنه.

توکن احراز هویت تپسی شاپ به صورت خودکار رفرش می‌شه. اگر API تپسی کد ۴۰۱ برگردونه، سرویس `TapsiShopService` توکن جدیدی دریافت کرده و درخواست رو دوباره ارسال می‌کنه.

---

## مپینگ SKU

برای هر محصول باید سه فیلد تنظیم بشه:

| فیلد | توضیح | مثال |
|------|-------|------|
| `tokeniko_sku` | نام محصول در API توکنیکو (کلید API) | `zioto-gold-bar-1gram-995` |
| `tapsi_product_id` | شناسه محصول در تپسی شاپ (ارسال به تپسی) | `ZGB5-0001-0` |
| `sku` | کد اختصاصی محصول در سایت (از قبل موجوده) | `ZGB5-0001-0` |

### شمش‌های طلا زیوتو (عیار ۹۹۵)

| نام محصول | `tokeniko_sku` | `tapsi_product_id` |
|-----------|---------------|-------------------|
| شمش طلا ۰.۵ گرم | `zioto-gold-bar-0.5gram-995` | `ZGB5-0000-5` |
| شمش طلا ۱ گرم | `zioto-gold-bar-1gram-995` | `ZGB5-0001-0` |
| شمش طلا ۲.۵ گرم | `zioto-gold-bar-2.5gram-995` | `ZGB5-0002-5` |
| شمش طلا ۵ گرم | `zioto-gold-bar-5gram-995` | `ZGB5-0005-0` |
| شمش طلا ۱۰ گرم | `zioto-gold-bar-10gram-995` | `ZGB5-0010-0` |
| شمش طلا ۲۰ گرم | `zioto-gold-bar-20gram-995` | `ZGB5-0020-0` |
| شمش طلا ۱ اونس | `zioto-gold-bar-1oz-995` | `ZGB5-0031-1` |
| شمش طلا ۵۰ گرم | `zioto-gold-bar-50gram-995` | `ZGB5-0050-0` |
| شمش طلا ۱۰۰ گرم | `zioto-gold-bar-100gram-995` | `ZGB5-0100-0` |
| شمش طلا ۲۵۰ گرم | `zioto-gold-bar-250gram-995` | `ZGB5-0250-0` |
| شمش طلا ۵۰۰ گرم | `zioto-gold-bar-500gram-995` | `ZGB5-0500-0` |
| شمش طلا ۱ کیلوگرم | `zioto-gold-bar-1kilogram-995` | `ZGB5-1000-0` |

### شمش‌های طلا زیوتو پلاس (عیار ۹۹۹.۹)

| نام محصول | `tokeniko_sku` | `tapsi_product_id` |
|-----------|---------------|-------------------|
| شمش طلا ۱ گرم پلاس | `ziotoplus-gold-bar-1gram-9999` | `ZPGB5-0001-0` |
| شمش طلا ۵ گرم پلاس | `ziotoplus-gold-bar-5gram-9999` | `ZPGB5-0005-0` |
| شمش طلا ۱۰ گرم پلاس | `ziotoplus-gold-bar-10gram-9999` | `ZPGB5-0010-0` |

### شمش‌های نقره زیوتو

| نام محصول | `tokeniko_sku` | `tapsi_product_id` |
|-----------|---------------|-------------------|
| شمش نقره ۱ اونس | `zioto-silver-bar-1oz` | `ZSB9-0031-1` |
| شمش نقره ۵۰ گرم | `zioto-silver-bar-50gram` | `ZSB9-0050-0` |
| شمش نقره ۱۰۰ گرم | `zioto-silver-bar-100gram` | `ZSB9-0100-0` |
| شمش نقره ۲۵۰ گرم | `zioto-silver-bar-250gram` | `ZSB9-0250-0` |
| شمش نقره ۵۰۰ گرم | `zioto-silver-bar-500gram` | `ZSB9-0500-0` |
| شمش نقره ۱ کیلوگرم | `zioto-silver-bar-1kilogram` | `ZSB9-1000-0` |
| شمش نقره ۲.۵ گرم | `zioto-silver-bar-2.5gram` | `ZSB9-0002-5` |
| شمش نقره ۵ گرم | `zioto-silver-bar-5gram` | `ZSB9-0005-0` |
| شمش نقره ۱۰ گرم | `zioto-silver-bar-10gram` | `ZSB9-0010-0` |
| شمش نقره ۱۵ گرم | `zioto-silver-bar-15gram` | `ZSB9-0015-0` |

### گرانول نقره زیوتو

| نام محصول | `tokeniko_sku` | `tapsi_product_id` |
|-----------|---------------|-------------------|
| گرانول نقره ۲۵ گرم | `zioto-silver-granules_25gram` | `ZSBB9-0025-0` |
| گرانول نقره ۵۰ گرم | `zioto-silver-granules_50gram` | `ZSBB9-0050-0` |
| گرانول نقره ۱۰۰ گرم | `zioto-silver-granules_100gram` | `ZSBB9-00100-0` |

### پک‌های هدیه نقره ۲.۵ گرم (قیمت مشترک)

> همه پک‌های هدیه ۲.۵ گرمی از قیمت `zioto-silver-bar-2.5gram` استفاده می‌کنن.

| نام محصول | `tokeniko_sku` | `tapsi_product_id` | قیمت از |
|-----------|---------------|-------------------|---------|
| پک هدیه گل کوکبی | `zioto-silver-bar-2.5g-gk` | `ZSB9-0002-5-GK` | `zioto-silver-bar-2.5gram` |
| پک هدیه گل لاله | `zioto-silver-bar-2.5g-gl` | `ZSB9-0002-5-GL` | `zioto-silver-bar-2.5gram` |
| پک هدیه گل سوسن | `zioto-silver-bar-2.5g-gs` | `ZSB9-0002-5-GS` | `zioto-silver-bar-2.5gram` |
| پک هدیه گل بنفشه | `zioto-silver-bar-2.5g-gb` | `ZSB9-0002-5-GB` | `zioto-silver-bar-2.5gram` |
| پک هدیه گل داودی | `zioto-silver-bar-2.5g-gd` | `ZSB9-0002-5-GD` | `zioto-silver-bar-2.5gram` |
| پک هدیه گل پیچک | `zioto-silver-bar-2.5g-gp` | `ZSB9-0002-5-GP` | `zioto-silver-bar-2.5gram` |
| پک هدیه بته جقه ساده | `zioto-silver-bar-2.5g-bs` | `ZSB9-0002-5-BS` | `zioto-silver-bar-2.5gram` |
| پک هدیه بته جقه آبی | `zioto-silver-bar-2.5g-ba` | `ZSB9-0002-5-BA` | `zioto-silver-bar-2.5gram` |
| پک هدیه بته جقه لوزی | `zioto-silver-bar-2.5g-bl` | `ZSB9-0002-5-BL` | `zioto-silver-bar-2.5gram` |
| پک هدیه بته جقه قبادی | `zioto-silver-bar-2.5g-bc` | `ZSB9-0002-5-BC` | `zioto-silver-bar-2.5gram` |
| پک هدیه بته جقه پرچمی | `zioto-silver-bar-2.5g-bp` | `ZSB9-0002-5-BP` | `zioto-silver-bar-2.5gram` |
| پک هدیه بته جقه مسجدی | `zioto-silver-bar-2.5g-bm` | `ZSB9-0002-5-BM` | `zioto-silver-bar-2.5gram` |

---

## فایل‌های جدید

### 1. `config/pricing.php`

تنظیمات حالت قیمت‌گذاری:

```php
'mode' => env('PRICING_MODE', 'dynamic'),
```

- `dynamic`: قیمت از تابلو قیمت + ضریب اجرت محاسبه می‌شه (رفتار فعلی)
- `direct`: قیمت مستقیماً از API توکنیکو گرفته می‌شه (رفتار وردپرس)

---

### 2. `config/tapsi.php`

تنظیمات اتصال به تپسی شاپ:

```php
'enabled'   => env('TAPSI_SYNC_ENABLED', false),   // روشن/خاموش کردن ارسال به تپسی
'auth_token' => env('TAPSI_AUTH_TOKEN', ''),
'auth_name'  => env('TAPSI_AUTH_NAME', 'zioto_sync_node'),
'base_url'   => env('TAPSI_API_BASE_URL', 'https://vendorgw.tapsi.shop/web/hub/vendors/v1'),
'markup_threshold'       => 50_000_000,
'markup_below_threshold' => env('TAPSI_MARKUP_BELOW_50M', 2),
'markup_above_threshold' => env('TAPSI_MARKUP_ABOVE_50M', 1),
```

- `enabled`: اگر `false` باشه، قیمت‌ها در دیتابیس آپدیت می‌شن ولی به تپسی ارسال **نمی‌شن**

---

### 3. `app/Services/TokenikoShopService.php`

سرویس دریافت قیمت از API مستقیم توکنیکو:

- **آدرس API:** `https://apigateway.tokeniko.com/shop/api/Category/getPrices`
- **بدون نیاز به authentication** (public)
- **خروجی:** آرایه‌ای با کلید = نام محصول (lowercase) و مقدار = قیمت به تومان
- **کش:** ۶۰ ثانیه

نحوه محاسبه قیمت:
```
قیمت تومان = SellPrice (از API) ÷ 10
```

(API توکنیکو قیمت رو به ریال برمی‌گردونه، تقسیم بر ۱۰ می‌کنیم تا تومان بشه)

---

### 4. `app/Services/TapsiShopService.php`

سرویس ارسال محصولات به تپسی شاپ:

- **ارسال batch:** `PUT /web/hub/vendors/v1/products`
- **رفرش توکن:** `POST /web/hub/vendors/v1/refresh-token`
- **هدر احراز هویت:** `TapsiShop.Hub.Authorization`
- **رفرش خودکار توکن** در صورت دریافت 401
- **ذخیره توکن جدید** در دیتابیس (Settings)

محاسبه قیمت برای تپسی:
```
اگر قیمت < ۵۰,۰۰۰,۰۰۰ تومان:
    قیمت تپسی = قیمت × ۱.۰۲ (۲٪ اضافه)
اگر قیمت >= ۵۰,۰۰۰,۰۰۰ تومان:
    قیمت تپسی = قیمت × ۱.۰۱ (۱٪ اضافه)

قیمت ریالی = قیمت تپسی تومان × ۱۰
```

---

### 5. `app/Console/Commands/Tokeniko/SyncTokenikoPrices.php`

کامند مستقل برای سینک مستقیم:

```bash
php artisan tokeniko:sync-direct
```

مراحل اجرا:
1. دریافت قیمت‌ها از توکنیکو (TokenikoShopService)
2. بررسی وضعیت اضطراری (Kill Switch)
3. پیدا کردن محصولاتی که `tokeniko_sku` دارن
4. مقایسه قیمت فعلی با قیمت جدید
5. آپدیت قیمت در دیتابیس (در صورت تغییر)
6. ساخت payload برای تپسی (با اعمال markup)
7. ارسال batch به تپسی شاپ
8. Broadcast رویداد قیمت‌ها از طریق Reverb/WebSocket

---

### 6. `database/migrations/2026_07_18_000001_add_tokeniko_and_tapsi_fields_to_products_table.php`

اضافه کردن دو فیلد به جدول products:

| فیلد | نوع | توضیح |
|------|------|-------|
| `tokeniko_sku` | string(50), nullable | نام محصول در API توکنیکو (مثلاً `zioto-gold-bar-1gram-995`) |
| `tapsi_product_id` | string(50), nullable | شناسه محصول در تپسی شاپ (مثلاً `ZGB5-0001-0`) |

---

## فایل‌های تغییر یافته

### 7. `.env` و `.env.example`

اضافه شده:
```env
PRICING_MODE=direct

TAPSI_SYNC_ENABLED=true
TAPSI_AUTH_TOKEN=
TAPSI_AUTH_NAME=zioto_sync_node
TAPSI_API_BASE_URL=https://vendorgw.tapsi.shop/web/hub/vendors/v1
TAPSI_MARKUP_BELOW_50M=2
TAPSI_MARKUP_ABOVE_50M=1
```

---

### 8. `Modules/Product/app/Models/Product.php`

اضافه شده به `$fillable`:
```php
'tokeniko_sku',
'tapsi_product_id',
```

---

### 9. `app/Console/Commands/Tokeniko/SyncPriceBoard.php`

تغییرات:
- در متد `handle()`: بررسی `config('pricing.mode')`
  - اگر `direct` باشه → فراخوانی `syncDirect()`
  - اگر `dynamic` باشه → رفتار قبلی (محاسبه از تابلو)
- متد `syncDirect()` جدید: منطق سینک مستقیم
- متد `broadcastProducts()`: بسته به حالت، محصولات مناسب رو broadcast می‌کنه
- متد `formatProduct()`: تعیین کلید مالیات بر اساس `price_board_item` یا `metal_type`

---

### 10. `app/Filament/Resources/Products/Schemas/ProductForm.php`

اضافه شده دو فیلد در بخش «اطلاعات عمومی»:
- **کد توکنیکو** (`tokeniko_sku`): نام محصول در API توکنیکو
- **کد تپسی شاپ** (`tapsi_product_id`): شناسه محصول در تپسی

---

### 11. `app/Filament/Pages/ManageSettings.php`

اضافه شده تب جدید **«تپسی شاپ»** شامل:
- **توکن احراز هویت** (`tapsi_auth_token`)
- **نام توکن** (`tapsi_auth_name`)
- **وضعیت اضطراری (Kill Switch)**: باز/بسته
  - بسته = موجودی همه محصولات ارسالی به تپسی صفر می‌شه
  - بدون آسیب به قیمت‌های اصلی سایت

ذخیره‌سازی:
- `tapsi_emergency_status` → در دیتابیس (Settings)
- `tapsi_auth_token`, `tapsi_auth_name` → در فایل `.env`

---

## نحوه استفاده

### فعال‌سازی حالت مستقیم

1. مایگریشن رو اجرا کنید:
```bash
php artisan migrate
```

2. در فایل `.env` مقدار زیر رو تنظیم کنید:
```env
PRICING_MODE=direct
```

3. توکن تپسی شاپ رو تنظیم کنید:
```env
TAPSI_AUTH_TOKEN=your_token_here
TAPSI_SYNC_ENABLED=true
```

4. برای هر محصول در فیلمنت، `tokeniko_sku` و `tapsi_product_id` رو پر کنید.

5. کامند سینک هر ۱ دقیقه اجرا می‌شه (از قبل تنظیم شده):
```bash
priceboard:sync  # این کامند حالا در حالت direct از توکنیکو مستقیم می‌گیره
```

### سوییچ به حالت تابلویی

فقط کافیه در `.env` تغییر بدید:
```env
PRICING_MODE=dynamic
```

بدون نیاز به تغییر کد یا دیتابیس.

### Kill Switch (وضعیت اضطراری)

مسیر در پنل فیلمنت:
```
/admin/settings → تب «تپسی شاپ» → بخش «کلید اضطراری (Kill Switch)»
```

> **نکته:** این تنظیم در دیتابیس ذخیره می‌شه (جدول `settings`) و از طریق ادمین قابل تغییر هست. جزو متغیرهای env نیست.

وضعیت‌ها:
- **باز (عادی)** → موجودی واقعی به تپسی ارسال می‌شه
- **بسته (اضطراری)** → موجودی صفر ارسال می‌شه تا فروش در تپسی متوقف بشه

موجودی تمام محصولات ارسالی به تپسی صفر می‌شه، اما:
- قیمت‌های اصلی سایت تغییر نمی‌کنه
- موجودی حسابفا تغییر نمی‌کنه

### غیرفعال کردن ارسال به تپسی (بدون تغییر حالت قیمت‌گذاری)

اگر فقط می‌خواید ارسال قیمت به تپسی شاپ متوقف بشه ولی قیمت‌ها همچنان از توکنیکو آپدیت بشن:
```env
TAPSI_SYNC_ENABLED=false
```

در این حالت:
- قیمت‌ها در دیتابیس آپدیت می‌شن
- Broadcast قیمت‌ها انجام می‌شه
- اما هیچ درخواستی به تپسی شاپ ارسال نمی‌شه

---

## نکات فنی

- **API توکنیکو:** public هست و نیاز به authentication نداره
- **قیمت‌ها:** لحظه‌ای هستن (طلا و نقره) و هر دقیقه آپدیت می‌شن
- **واحدها:** API توکنیکو قیمت رو به ریال برمی‌گردونه. تقسیم بر ۱۰ → تومان. ضرب در ۱۰ → ریال برای تپسی
- **کش:** قیمت‌های توکنیکو ۶۰ ثانیه کش می‌شن تا فشار به API کم بشه
- **بدون حذف کد:** کد قبلی (حالت تابلویی) کاملاً حفظ شده و فقط با یک if/else سوییچ می‌شه

---

## خلاصه متغیرهای ENV

| متغیر | پیش‌فرض | توضیح |
|-------|---------|-------|
| `PRICING_MODE` | `dynamic` | حالت قیمت‌گذاری (`dynamic` یا `direct`) |
| `TAPSI_SYNC_ENABLED` | `false` | روشن/خاموش ارسال به تپسی شاپ |
| `TAPSI_AUTH_TOKEN` | `''` | توکن احراز هویت تپسی شاپ |
| `TAPSI_AUTH_NAME` | `zioto_sync_node` | نام توکن |
| `TAPSI_API_BASE_URL` | `https://vendorgw.tapsi.shop/web/hub/vendors/v1` | آدرس API تپسی |
| `TAPSI_MARKUP_BELOW_50M` | `2` | درصد markup زیر ۵۰ میلیون تومان |
| `TAPSI_MARKUP_ABOVE_50M` | `1` | درصد markup بالای ۵۰ میلیون تومان |

> **کلید اضطراری (Kill Switch):** در جدول `settings` دیتابیس ذخیره می‌شه و از پنل فیلمنت قابل تغییر هست. جزو متغیرهای env نیست.

---

## تست

```bash
vendor/bin/pint --dirty --format agent    # بررسی فرمت کد
php artisan test --compact                # اجرای تست‌ها
php artisan tokeniko:sync-direct          # تست دستی سینک مستقیم
php artisan priceboard:sync               # تست سینک (بسته به PRICING_MODE)
```
