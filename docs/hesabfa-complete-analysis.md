# تحلیل جامع یکپارچه‌سازی حسابفا (Hesabfa) در پروژه ZIOTO

> تاریخ: ۲۷ جولای ۲۰۲۶  
> نسخه تحلیل: ۱.۰

---

## فهرست

1. [معماری کلی و لایه‌ها](#۱-معماری-کلی-و-لایه‌ها)
2. [تنظیمات و پیکربندی](#۲-تنظیمات-و-پیکربندی)
3. [کالبدشکافی سرویس API (HesabfaService)](#۳-کالبدشکافی-سرویس-api-hesabfaservice)
4. [کالبدشکافی Observer سفارشات (HesabfaObserver)](#۴-کالبدشکافی-observer-سفارشات-hesabfaobserver)
5. [کالبدشکافی سرویس همگام‌سازی موجودی (StockSyncService)](#۵-کالبدشکافی-سرویس-همگام‌سازی-موجودی-stocksyncservice)
6. [کالبدشکافی رزرو موجودی (StockReservationObserver)](#۶-کالبدشکافی-رزرو-موجودی-stockreservationobserver)
7. [کالبدشکافی وب‌هوک (HesabfaWebhookController)](#۷-کالبدشکافی-وب‌هوک-hesabfawebhookcontroller)
8. [کالبدشکافی دستور Artisan (SyncHesabfaStock)](#۸-کالبدشکافی-دستور-artisan-synchesabfastock)
9. [پنل ادمین (Filament)](#۹-پنل-ادمین-filament)
10. [ساختار دیتابیس](#۱۰-ساختار-دیتابیس)
11. [زنجیره رویدادها (Event Chain)](#۱۱-زنجیره-رویدادها-event-chain)
12. [عوارض جانبی (Side Effects)](#۱۲-عوارض-جانبی-side-effects)
13. [مسائل امنیتی](#۱۳-مسائل-امنیتی)
14. [مشکلات Race Condition](#۱۴-مشکلات-race-condition)
15. [مسائل و خطرات شناخته‌شده](#۱۵-مسائل-و-خطرات-شناخته‌شده)
16. [نقاط ضعف در لاگینگ](#۱۶-نقاط-ضعف-در-لاگینگ)
17. [خلاصه مشکلات بحرانی](#۱۷-خلاصه-مشکلات-بحرانی)

---

## ۱. معماری کلی و لایه‌ها

ادغام حسابفا در پروژه از ۴ لایه اصلی تشکیل شده:

```
┌─────────────────────────────────────────────────────┐
│ لایه UI (Filament Dashboard + Blade Views)          │
│ → HesabfaDashboard, HesabfaSyncLogs, ManageSettings  │
├─────────────────────────────────────────────────────┤
│ لایه Scheduler (Cron + Artisan Commands)             │
│ → SyncHesabfaStock, AppServiceProvider schedule      │
├─────────────────────────────────────────────────────┤
│ لایه Business Logic (Observers + Services)           │
│ → HesabfaObserver, StockReservationObserver          │
│ → StockSyncService                                   │
├─────────────────────────────────────────────────────┤
│ لایه API Client (HesabfaService)                     │
│ → ۹ endpoint مختلف حسابفا                           │
└─────────────────────────────────────────────────────┘
```

### ۱.۱. فایل‌های پروژه

| دسته | فایل | خطوط | نقش |
|------|------|-------|------|
| Config | `config/hesabfa.php` | ۸۱ | تمام تنظیمات حسابفا |
| Config | `config/logging.php` (lines 138-144) | ۶ | کانال لاگ اختصاصی `hesabfa` |
| Service | `app/Services/HesabfaService.php` | ۲۹۳ | کلاینت API سطح پایین |
| Service | `app/Services/StockSyncService.php` | ۱۵۴ | منطق همگام‌سازی موجودی |
| Observer | `app/Observers/HesabfaObserver.php` | ۴۷۰ | ارسال خودکار سفارش‌ها |
| Observer | `app/Observers/StockReservationObserver.php` | ۱۰۲ | رزرو/آزادسازی موجودی |
| Command | `app/Console/Commands/SyncHesabfaStock.php` | ۳۵ | دستور `hesabfa:sync-stock` |
| Controller | `app/Http/Controllers/Api/HesabfaWebhookController.php` | ۱۳۲ | دریافت رویدادهای لحظه‌ای |
| Routes | `routes/api.php` (lines 68-71) | ۴ | ۲ مسیر webhook |
| Model | `app/Models/HesabfaSyncLog.php` | ۳۴ | مدل لاگ همگام‌سازی |
| Model | `Modules/Order/app/Models/Order.php` | ۱۰۲ | ۴ فیلد حسابفا + رابطه |
| Model | `Modules/Product/app/Models/Product.php` | ۱۷۱ | ۶ فیلد حسابفا + موجودی قابل فروش |
| Provider | `app/Providers/AppServiceProvider.php` | ۶۶ | ثبت Observer + زمان‌بندی Cron |
| Migrations | `2026_06_22_104051...` | - | ۴ ستون orders |
| Migrations | `2026_06_22_104052...` | - | جدول hesabfa_sync_log |
| Migrations | `2026_06_22_113438...` | - | ۶ ستون products |
| Migrations | `2026_07_20_000001...` | - | ایندکس‌های عملکردی |
| Filament | `app/Filament/Pages/Hesabfa/HesabfaDashboard.php` | ۱۰۱ | داشبورد حسابفا |
| Filament | `app/Filament/Pages/Hesabfa/HesabfaSyncLogs.php` | ۱۱۱ | لاگ همگام‌سازی |
| Filament | `app/Filament/Pages/ManageSettings.php` | ۳۸۸ | تب تنظیمات حسابفا |
| Blade | `resources/views/filament/pages/hesabfa-dashboard.blade.php` | ۲۱۳ | قالب داشبورد |
| Blade | `resources/views/filament/pages/hesabfa-sync-logs.blade.php` | ۹۸ | قالب لاگ |
| Seeder | `database/seeders/RolePermissionSeeder.php` | - | Permission `manage-hesabfa` |

---

## ۲. تنظیمات و پیکربندی

همه تنظیمات از `config/hesabfa.php` خوانده می‌شوند که از متغیرهای محیطی `.env` تغذیه می‌کند.

### ۲.۱. جدول کامل تنظیمات

| کلید Config | متغیر Env | پیش‌فرض | جای تغییر | توضیح |
|-------------|-----------|---------|-----------|-------|
| `api_key` | `HESABFA_API_KEY` | `''` | `.env` / Settings UI | کلید API حسابفا |
| `login_token` | `HESABFA_LOGIN_TOKEN` | `''` | `.env` / Settings UI | توکن ورود |
| `base_url` | `HESABFA_BASE_URL` | `https://api.hesabfa.com/v1` | `.env` | آدرس پایه API |
| `default_project` | `HESABFA_DEFAULT_PROJECT` | `سایت ZIOTO` | `.env` / Settings UI | نام پروژه در فاکتور |
| `default_unit` | - | `عدد` | Hardcoded | واحد پیش‌فرض اقلام |
| `draft_invoice` | `HESABFA_DRAFT_INVOICE` | `true` | `.env` / Settings UI | پیش‌نویس بودن فاکتور |
| `use_current_date` | `HESABFA_USE_CURRENT_DATE` | `false` | `.env` / Settings UI | استفاده از تاریخ امروز |
| `shipping_item_code` | `HESABFA_SHIPPING_ITEM_CODE` | `''` | `.env` / Settings UI | کد کالای حمل و نقل |
| `installment_fee_item_code` | `HESABFA_INSTALLMENT_FEE_ITEM_CODE` | `''` | `.env` / Settings UI | کد کالای کارمزد اقساط |
| `warehouse_code` | `HESABFA_WAREHOUSE_CODE` | `11` | `.env` / Settings UI | کد انبار |
| `enable_warehouse_receipt` | `HESABFA_ENABLE_WAREHOUSE_RECEIPT` | `false` | `.env` / Settings UI | رسید انبار خودکار |
| `customer_node` | `HESABFA_CUSTOMER_NODE` | `''` | `.env` / Settings UI | گره مشتریان |
| `customer_family` | `HESABFA_CUSTOMER_FAMILY` | `''` | `.env` / Settings UI | خانواده مشتریان |
| `auto_sync` | `HESABFA_AUTO_SYNC` | `true` | `.env` / Settings UI | ارسال خودکار سفارشات |
| `sync_statuses` | - | `['confirmed']` | Hardcoded | وضعیت‌های قابل همگام |
| `sync_stock` | `HESABFA_SYNC_STOCK` | `true` | `.env` / Settings UI | همگام‌سازی موجودی |
| `sync_interval` | `HESABFA_SYNC_INTERVAL` | `60` | `.env` / Settings UI | فاصله Cron به دقیقه |
| `enable_reserved_stock` | `HESABFA_ENABLE_RESERVED_STOCK` | `false` | `.env` / Settings UI | فعال‌سازی رزرو موجودی |
| `excluded_skus` | `HESABFA_EXCLUDED_SKUS` | `[]` | `.env` | SKU‌های معاف از سینک |
| `webhook_secret` | `HESABFA_WEBHOOK_SECRET` | `''` | `.env` / Settings UI | راز وب‌هوک |

### ۲.۲. نکته مهم: `enable_reserved_stock` هیچ‌جا استفاده نمی‌شود

کلید `hesabfa.enable_reserved_stock` در هیچ فایلی بررسی نمی‌شود. `StockReservationObserver` بدون توجه به این تنظیم کار می‌کند. این احتمالاً یک ویژگی ناقص یا اشتباه است.

### ۲.۳. ذخیره‌سازی تنظیمات از طریق پنل

در `ManageSettings.php:318-354`، تنظیمات حسابفا مستقیماً در فایل `.env` نوشته می‌شوند. این روش:
- نیازمند **دسترسی نوشتن** به فایل `.env` است (مشکل در محیط‌های production با permission محدود)
- در محیط containerized (مثل Docker) معمولاً `.env` خواندنی است → نوشتن در آن fail می‌شود
- تغییرات تنها پس از **restart** اپلیکیشن اعمال می‌شوند (در production با OPcache نیاز به cache clear)

---

## ۳. کالبدشکافی سرویس API (HesabfaService)

### ۳.۱. ساختار

```php
class HesabfaService
{
    private string $apiKey;
    private string $loginToken;
    private string $baseUrl;
}
```

این سرویس به صورت مستقیم با Hesabfa API ارتباط برقرار می‌کند و هیچ آبجکشن یا interface ای ندارد.

### ۳.۲. متدهای عمومی

| متد | Endpoint حسابفا | پارامترها | خروجی |
|------|----------------|-----------|-------|
| `isConfigured()` | - | - | `bool` |
| `testConnection()` | `GET /contact/getcontacts` | - | `['success', 'message']` |
| `findContactByNationalCode($nationalCode)` | `POST /contact/getcontacts` | کد ملی | `?array` |
| `saveContact($contactData)` | `POST /contact/save` | اطلاعات مشتری | `array` |
| `getItemByCode($itemCode)` | `POST /item/get` | کد کالا | `?array` |
| `findItemBySku($sku)` | `POST /item/getitems` | SKU | `?array` |
| `getAllItemQuantities($warehouseCode)` | `POST /item/GetQuantity` | کد انبار | `array` |
| `saveInvoice($invoiceData)` | `POST /invoice/save` | داده فاکتور | `array` |
| `saveWarehouseReceipt($invoiceNumber)` | `POST /invoice/SaveWarehouseReceipt` | شماره فاکتور | `array` |
| `findInvoiceByReference($reference)` | `POST /invoice/getinvoices` | مرجع | `?array` |
| `getConfirmedInvoices($date)` | `POST /invoice/getinvoices` | تاریخ | `array` |
| `getInvoiceDetails($invoiceNumber)` | `POST /invoice/get` | شماره فاکتور | `?array` |

### ۳.۳. مکانیزم Retry (مهم)

متد خصوصی `call()` در خط ۲۰۵ تا ۲۹۲:

```php
private function call(string $endpoint, array $payload, int $maxRetries = 3): array
```

**رفتار Retry:**
- Retry فقط در دو حالت انجام می‌شود: `HTTP 429` (Rate limit) یا `HTTP >= 500` (Server Error)
- در صورت `Exception` (ارتباطی)، retry انجام می‌شود
- Retry با تأخیر تصاعدی: `sleep($attempt * 2)` یعنی ۲، ۴، ۶ ثانیه
- در صورت **HTTP 4xx** (به جز ۴۲۹)، بلافاصله fail می‌شود بدون retry
- در صورت **Success Response** اما `Success === false` در بدنه، بلافاصله fail می‌شود

**نکته بحرانی:** لاگ خطا در `$sanitized` خط ۲۱۴-۲۱۹ `apiKey` و `loginToken` را حذف می‌کند، اما اگر `Exception` رخ دهد و `$payload` در catch استفاده شود، لاگ شامل credentialها خواهد بود. در کد فعلی این اتفاق نمی‌افتد چون در catch فقط `$e->getMessage()` لاگ می‌شود.

### ۳.۴. نحوه احراز هویت

API Key و Login Token در **بدنه درخواست** ارسال می‌شوند (نه هدر):

```php
$payload['apiKey'] = $this->apiKey;
$payload['loginToken'] = $this->loginToken;
// ...
->withBody(json_encode($payload, JSON_UNESCAPED_UNICODE), 'application/json')
```

این یک استاندارد امنیتی ضعیف است (credentialها در body)، اما تابع API حسابفاست و راه گریزی نیست.

### ۳.۵. Timeout

تمامی درخواست‌ها با `Http::timeout(30)` انجام می‌شوند یعنی ۳۰ ثانیه. برای عملیات批量 مثل `GetQuantity` که ممکن است دیتای زیادی برگرداند، این زمان ممکن است کافی نباشد.

---

## ۴. کالبدشکافی Observer سفارشات (HesabfaObserver)

### ۴.۱. شرایط فعال شدن (مهم)

```php
public function updated(Order $order): void
```

این متد در **هر بار آپدیت شدن سفارش** صدا زده می‌شود، اما سنکرون فقط در صورت ALL شرایط زیر انجام می‌شود:

1. **`hesabfa.auto_sync`** باید `true` باشد
2. **`hesabfa_synced_at`** باید `null` باشد (هنوز ارسال نشده)
3. **وضعیت سفارش** به `sync_statuses` تغییر کرده باشد (پیش‌فرض: `['confirmed']`)
4. اگر قبلاً sync شده و status تغییر کرده → **شروع نشود** (خط ۲۳-۲۵)

**سناریوهای دقیق:**

| وضعیت قبلی | وضعیت جدید | hesabfa_synced_at | نتیجه |
|------------|-----------|-------------------|--------|
| pending | confirmed | null | ✅ sync انجام می‌شود |
| pending | confirmed | not null | ❌ رد می‌شود (خط ۲۳-۲۵) |
| confirmed | completed | not null | ❌ رد می‌شود (قبلاً sync شده) |
| pending | cancelled | null | ❌ وضعیت در sync_statuses نیست |
| pending | pending | null | ❌ وضعیت تغییر نکرده |
| confirmed | cancelled | not null | ❌ auto_sync نمی‌شود (اما stock reservation release می‌شود) |

**نکته بسیار مهم:** اگر `hesabfa_synced_at` از قبل set شده باشد اما `status` تغییر کرده باشد، `sync` مجدد انجام نمی‌شود. یعنی اگر فاکتوری در حسابفا fail شود و نیاز به resync داشته باشد، باید `hesabfa_synced_at` را null کرد (از طریق پنل ادمین یا دیتابیس).

### ۴.۲. جریان syncOrder

```
syncOrder($order, $force = false)
    │
    ├── بررسی isConfigured()
    ├── بررسی hesabfa_synced_at (رد اگر set هست و force نباشد)
    │
    ├── syncContact() ────► ۱) extractNationalCode()  ← user.national_code / address.receiver_national_code
    │                         ۲) normalizeNationalCode()  ← تبدیل ارقام فارسی/عربی به لاتین
    │                         ۳) findContactByNationalCode()  ← GET /contact/getcontacts
    │                         ۴) buildContactData()  ← ساخت پیلود مشتری
    │                         ۵) saveContact()  ← POST /contact/save
    │                         ۶) استخراج contactCode از response
    │
    └── syncInvoice() ────► ۱) buildInvoiceReference()  ← {orderId}-{floor(total/10,000,000)}
                              ۲) buildInvoiceItems()
                              ۳) buildInvoicePayload()
                              ۴) saveInvoice()  ← POST /invoice/save
                              ۵) saveWarehouseReceipt()  ← POST /invoice/SaveWarehouseReceipt
```

### ۴.۳. نحوه ساخت مرجع فاکتور (Reference)

```php
private function buildInvoiceReference(Order $order): string
{
    $totalInRials = (int) $order->total_amount;
    $tenMillions = (int) floor($totalInRials / 10_000_000);
    return "{$order->id}-{$tenMillions}";
}
```

**مثال:** سفارش `#21000` با مبلغ `150,000,000` ریال → مرجع: `21000-15`

**ریسک Collision:** اگر سفارش $order->id = 12345 با دو مبلغ مختلف (مثلاً 90,000,000 و 95,000,000 ریال) داشته باشیم، مرجع یکسان می‌شود: `12345-9`. هرچند Hesabfa احتمالاً Reference یکتا را مجبور می‌کند، اما طراحی فعلی این ریسک را دارد.

### ۴.۴. نحوه ساخت آیتم‌های فاکتور

اقلام فاکتور از ۳ بخش تشکیل می‌شوند:

1. **محصولات اصلی** (`buildProductItems`):
   - هر آیتم سفارش → یک ردیف فاکتور
   - جستجوی کد کالا در حسابفا با `findItemBySku()` (تماس API)
   - اگر SKU در حسابفا یافت نشود → کل فاکتور **با failure متوقف می‌شود**

2. **حمل و نقل** (`buildShippingItem`):
   - فقط اگر `shipping_item_code` تنظیم شده باشد
   - فقط اگر `shipping_cost > 0`

3. **کارمزد اقساط** (`buildInstallmentFeeItem`):
   - فقط اگر `payment_method === 'installment'`
   - فقط اگر `installment_fee_item_code` تنظیم شده باشد
   - محاسبه کارمزد: `4% از (جمع اقلام + هزینه ارسال)`
   - مالیات ۱۰% روی کارمزد محاسبه می‌شود

### ۴.۵. عوارض جانبی (Side Effects) در HesabfaObserver

**در صورت موفقیت:**
- ۴ فیلد روی سفارش set می‌شود: `hesabfa_contact_code`, `hesabfa_invoice_number`, `hesabfa_invoice_reference`, `hesabfa_synced_at`
- یک یادداشت (`addNote`) از نوع `hesabfa` با `isCustomerNote = true` اضافه می‌شود

**در صورت خطا:**
- لاگ `HesabfaSyncLog` با status `failed` ثبت می‌شود
- یادداشت خطا روی سفارش اضافه می‌شود
- **سفارش تا sync شدن مجدد در وضعیت "ارسال نشده" باقی می‌ماند** (`hesabfa_synced_at` تغییر نمی‌کند)

### ۴.۶. لاگینگ در HesabfaObserver

لاگ‌ها با `HesabfaSyncLog::create()` ثبت می‌شوند:

| sync_type | زمان ثبت | status |
|-----------|---------|--------|
| `contact` | فقط در خطای contact | `failed` |
| `invoice` | فقط در خطای invoice | `failed` |
| `full_sync` | فقط در Exception کلی | `failed` |
| success | **هیچ‌وقت** | - |

این یعنی **ثبت موفقیت‌های همگام‌سازی در دیتابیس انجام نمی‌شود**. برای گزارش‌گیری از موفقیت‌ها، فقط به `hesabfa_synced_at` می‌توان اتکا کرد.

---

## ۵. کالبدشکافی سرویس همگام‌سازی موجودی (StockSyncService)

### ۵.۱. جریان syncAllStock

```
syncAllStock()
    │
    ├── ۱) بررسی isConfigured()
    ├── ۲) بررسی config('hesabfa.sync_stock')
    ├── ۳) getAllItemQuantities(warehouseCode)  ← POST /item/GetQuantity
    ├── ۴) prepareStockUpdates()
    │       ├── filter: فقط آیتم‌های دارای ItemCode/ProductCode
    │       ├── map: استخراج sku و quantity (max(0, ...))
    │       ├── reject: حذف excluded_skus
    │       └── unique: حذف SKU تکراری
    └── ۵) batchUpdateStock()
            ├── chunk(100) روی مجموعه
            ├── Product::whereIn('sku', $skus) →
            ├── اگر hesabfa_stock_locked → رد شود
            └── update([stock_quantity, hesabfa_physical_stock, hesabfa_stock_synced_at])
```

### ۵.۲. نکات مهم در syncAllStock

- **فیلتر quantity:** `max(0, (int) ($item['Quantity'] ?? $item['Physical'] ?? 0))` → منفی صفر می‌شود
- **توجه:** `stock_quantity` و `hesabfa_physical_stock` هر دو به یک مقدار set می‌شوند
- **Batch size:** chunk 100 تایی
- **An error in one product doesn't stop others**
- **هیچ لاگی برای محصولات به‌روز شده ثبت نمی‌شود** (فقط در خطاها لاگ می‌شود)

### ۵.۳. Stock Lock (قفل موجودی)

در `batchUpdateStock` (خط ۷۴) بررسی می‌شود:
```php
if ($product->hesabfa_stock_locked) {
    continue;
}
```

اما `hesabfa_stock_locked` در وب‌هوک نیز بررسی می‌شود (در `updateStockByItemCode` خط ۱۱۴).

**نکته مهم:** `hesabfa_stock_locked` فقط از سمت همگام‌سازی (دریافت موجودی از حسابفا) محافظت می‌کند، اما **از سمت پنل ادمین قابل تغییر است** و هیچ لاگی از تغییر آن ثبت نمی‌شود.

### ۵.۴. وب‌هوک Stock (updateStockByItemCode)

این متد توسط webhook صدا زده می‌شود:
- محصول را با `sku` پیدا می‌کند
- بررسی `excluded_skus`
- بررسی `hesabfa_stock_locked`
- `stock_quantity` و `hesabfa_physical_stock` را آپدیت می‌کند
- فقط یک Log سطح INFO ثبت می‌کند (نه در دیتابیس، فقط در فایل)

### ۵.۵. وب‌هوک Price (updatePriceByItemCode)

```php
$priceInToman = (int) round($priceInRials / 10);
$product->update(['price' => $priceInToman]);
```

این متد:
- قیمت را از ریال به تومان تبدیل می‌کند (تقسیم بر ۱۰)
- **توجه:** `$priceInRials` از webhook می‌آید و نوع آن `int` است
- **ریسک:** اگر حسابفا قیمت را به تومان ارسال کند، تقسیم بر ۱۰ باعث اشتباه فاحش می‌شود

---

## ۶. کالبدشکافی رزرو موجودی (StockReservationObserver)

### ۶.۱. رویدادها و رفتار

| رویداد | شرط | عملکرد |
|--------|-----|--------|
| `created(Order)` | status در `['confirmed']` باشد | `reserveStock()` |
| `updated(Order)` | status از غیر confirmed به confirmed | `reserveStock()` |
| `updated(Order)` | status از confirmed به غیر confirmed | `releaseStock()` |
| `deleted(Order)` | status در `['confirmed']` باشد | `releaseStock()` |

### ۶.۲. مکانیزم رزرو (reserveStock)

```php
$updated = DB::table('products')
    ->where('id', $productId)
    ->whereRaw('hesabfa_physical_stock - hesabfa_reserved_stock - hesabfa_manual_reserved >= ?', [$quantity])
    ->increment('hesabfa_reserved_stock', $quantity);
```

این یک **عملیات اتمی** در سطح دیتابیس است:
- مستقیماً از `DB::table()` استفاده می‌کند (نه Eloquent)
- بررسی می‌کند موجودی قابل فروش کافی هست یا نه
- اگر `$updated === 0` یعنی موجودی کافی نبوده → فقط **warning لاگ** می‌شود

**نکته بحرانی:** رزرو موجودی تا حدی اتمی است (یک query)، اما اگر دو سفارش همزمان برای یک محصول ثبت شوند:
1. ممکن است هر دو `hesabfa_physical_stock` یکسان را ببینند (بسته به سطح ایزولیشن MySQL)
2. هر دو increment انجام شود و `hesabfa_reserved_stock` از `hesabfa_physical_stock` بیشتر شود
3. منجر به موجودی قابل فروش منفی می‌شود

### ۶.۳. مکانیزم آزادسازی (releaseStock)

```php
DB::table('products')
    ->where('id', $productId)
    ->decrement('hesabfa_reserved_stock', $quantity);
```

هیچ محدودیتی برای decrement وجود ندارد. یعنی اگر `hesabfa_reserved_stock` از `quantity` کمتر باشد، **منفی می‌شود** (بسته به نوع ستون که decimal است).

### ۶.۴. عدم وجود Transaction

اگر یک سفارش ۵ محصول داشته باشد و رزرو محصول سوم fail شود، رزرو محصولات اول و دوم **برگردانده نمی‌شود**. 

### ۶.۵. عدم وجود Double-Reserve Prevention

اگر سفارشی دوباره `updated` شود (با status تغییر نکند)، `reserveStock` دوباره صدا زده نمی‌شود. درست است چون فقط تغییر status بررسی می‌شود (خط ۲۷-۳۴). اما اگر status از confirmed → cancelled → confirmed برگردد:
1. بار اول: `reserveStock()` (موجودی رزرو می‌شود)
2. بار دوم: `releaseStock()` (موجودی آزاد می‌شود)
3. بار سوم: `reserveStock()` (دوباره رزرو می‌شود) ✅ درست

---

## ۷. کالبدشکافی وب‌هوک (HesabfaWebhookController)

### ۷.۱. مسیرها

| Method | Route | Handler | توضیح |
|--------|-------|---------|-------|
| POST | `/api/hesabfa/webhook` | `handle()` | دریافت رویدادهای حسابفا |
| GET | `/api/hesabfa/webhook` | `test()` | بررسی وضعیت اتصال |

### ۷.۲. فرآیند احراز هویت

```php
$providedSecret = $request->header('X-Webhook-Secret')
    ?? $request->input('secret')
    ?? $request->query('secret');
```

وب‌هوک از ۳ روش رمز را دریافت می‌کند:
1. هدر `X-Webhook-Secret`
2. پارامتر POST `secret`
3. پارامتر Query `secret` (⚠️ این می‌تواند در لاگ‌های سرور ثبت شود)

**نکته امنیتی مهم:** وجود `$request->query('secret')` یعنی ممکن است شخصی لینکی مانند `/api/hesabfa/webhook?secret=XXXX` در تاریخچه مرورگر یا لاگ‌های پراکسی ثبت کند.

### ۷.۳. رویدادهای پشتیبانی شده

| EventType | Handler | توضیح |
|-----------|---------|-------|
| `ItemQuantityChanged` | `handleQuantityChange()` | بروزرسانی موجودی کالا |
| `ItemPriceChanged` | `handlePriceChange()` | بروزرسانی قیمت کالا |
| سایر | `Unhandled event` | فقط پیام در response |

### ۷.۴. عوارض جانبی وب‌هوک

- فایل‌های لاگ مجزا در `storage/logs/hesabfa-webhooks/webhook-YYYY-MM-DD.log`
- نگهداری ۳۰ روز لاگ (با پاکسازی خودکار)
- API Key وب‌هوک (secret) در لاگ نمی‌نویسد (در بدنه درخواست ذخیره می‌کند که شامل secret پارامتر query/post است... **بله اگر secret در query string بیاید در فایل لاگ ذخیره می‌شود**)
- **تماس مستقیم با StockSyncService بدون قفل یا صف** - اگر ۱۰۰تا webhook همزمان برسد، همه به صورت هم‌زمان دیتابیس را update می‌کنند

### ۷.۵. مسائل امنیتی Webhook

1. **بدون Rate Limiting:** هیچ محدودیت نرخی روی webhook نیست
2. **بدون Authentication در Route:** route فقط از middleware `web` استفاده می‌کند، احراز هویت دستی است
3. **بدون HTTPS Enforcement**
4. **Logging Secret:** اگر secret در query string باشد، در فایل لاگ ثبت می‌شود

---

## ۸. کالبدشکافی دستور Artisan (SyncHesabfaStock)

### ۸.۱. زمان‌بندی

در `AppServiceProvider.php:41-42`:

```php
$interval = config('hesabfa.sync_interval', 60);
Schedule::command('hesabfa:sync-stock')->cron("*/{$interval} * * * *");
```

با `sync_interval = 60`:
- `*/60 * * * *` یعنی هر ساعت یک بار در دقیقه ۰

با `sync_interval = 30`:
- `*/30 * * * *` یعنی هر ۳۰ دقیقه

### ۸.۲. محدودیت‌ها

- اگر `syncAllStock()` بیش از ۶۰ دقیقه طول بکشد، دو instance هم‌زمان اجرا می‌شوند
- از `withoutOverlapping()` استفاده نمی‌کند → ریسک اجرای همزمان
- **مهم:** اگر Sync مدت زیادی طول بکشد، Cron بعدی هم شروع می‌شود

---

## ۹. پنل ادمین (Filament)

### ۹.۱. HesabfaDashboard

| Feature | پیاده‌سازی | جزئیات |
|---------|-----------|--------|
| Status API | تست اتصال با `testConnection()` | نمایش وضعیت |
| آمار سفارشات | `Order::count()`, `whereNotNull('hesabfa_synced_at')` | کل / sync شده / نشده |
| Sync Stock Button | `StockSyncService::syncAllStock()` | اجرای هم‌زمان (ممکن است timeout شود) |
| Recent Activity | آخرین ۱۰ HesabfaSyncLog | فعالیت‌های اخیر |
| Recent Errors | آخرین ۵ HesabfaSyncLog failed | خطاهای اخیر |

### ۹.۲. ManageSettings (تب حسابفا)

تنظیمات قابل تغییر از پنل:
- API Key, Login Token
- نام پروژه، کد انبار
- پیش‌نویس/تاریخ امروز
- کد کالاهای حمل و کارمزد
- گره و خانواده مشتری
- Auto Sync, Sync Stock, Interval
- Warehouse Receipt, Reserved Stock
- Webhook Secret

**مکانیزم ذخیره (ManagerSettings.php:318-354):**
1. فایل `.env` را می‌خواند
2. مقدار متغیرها را جایگزین می‌کند
3. فایل را ذخیره می‌کند

**ریسک:** اگر دستور `file_get_contents(base_path('.env'))` fail شود، فایل خالی ذخیره می‌شود و کل اپلیکیشن از کار می‌افتد.

### ۹.۳. HesabfaSyncLogs

| قابلیت | پیاده‌سازی |
|--------|-----------|
| Filter by Type | `where('sync_type', $this->filterType)` |
| Filter by Status | `where('status', $this->filterStatus)` |
| Pagination | `paginate(15)` |
| Sync Type Badge | full_sync → success, contact → info, invoice → warning |
| Status Badge | success → success, failed → danger |

### ۹.۴. Order Resources (Hesabfa در فرم سفارش)

**OrderForm.php:221-244**: بخش "اطلاعات حسابفا" با ۳ فیلد disabled:
- `hesabfa_contact_code` (کد مشتری)
- `hesabfa_invoice_number` (شماره فاکتور)
- `hesabfa_synced_at` (تاریخ همگام‌سازی)

**EditOrder.php:28-50**: دکمه "ارسال به حسابفا" که:
- فقط برای سفارش‌های **sync نشده** نمایش داده می‌شود
- `HesabfaObserver::syncOrder($record, force: true)` را صدا می‌زند
- بعد از sync، فرم را رفرش می‌کند

**OrdersTable.php:123-128**: ستون "حسابفا" در جدول سفارشات:
- اگر `hesabfa_invoice_number` داشته باشد → badge سبز "فاکتور #N"
- اگر null باشد → badge خاکستری "ارسال نشده"

**ViewOrder.php:253-266**: نمایش اطلاعات حسابفا در سایدبار:
- کد مشتری حسابفا
- شماره فاکتور حسابفا
- تاریخ همگام‌سازی حسابفا

### ۹.۵. Product Resources (Hesabfa در فرم محصول)

**ProductForm.php:236-292**: بخش "تنظیمات حسابفا" با فیلدهای:
- `hesabfa_physical_stock` (disabled) - موجودی فیزیکی
- `hesabfa_reserved_stock` (disabled) - موجودی رزرو شده
- `sellable_stock` (disabled) - موجودی قابل فروش (محاسبه شده)
- `hesabfa_manual_reserved` (editable) - رزرو دستی
- `hesabfa_exclude_from_sync` (toggle) - غیرفعال کردن سینک
- `hesabfa_stock_locked` (toggle) - قفل سینک
- `hesabfa_stock_synced_at` (disabled) - آخرین همگام‌سازی

---

## ۱۰. ساختار دیتابیس

### ۱۰.۱. جدول `orders` (فیلدهای حسابفا)

```sql
ALTER TABLE orders ADD COLUMN hesabfa_contact_code      VARCHAR(50) NULL;
ALTER TABLE orders ADD COLUMN hesabfa_invoice_number    BIGINT UNSIGNED NULL;
ALTER TABLE orders ADD COLUMN hesabfa_invoice_reference VARCHAR(50) NULL;
ALTER TABLE orders ADD COLUMN hesabfa_synced_at         TIMESTAMP NULL;
```

و ایندکس: `INDEX idx_orders_status_hesabfa_synced (status, hesabfa_synced_at)`

### ۱۰.۲. جدول `products` (فیلدهای حسابفا)

```sql
ALTER TABLE products ADD COLUMN hesabfa_physical_stock   DECIMAL(10,2) NULL;
ALTER TABLE products ADD COLUMN hesabfa_reserved_stock   DECIMAL(10,2) NULL;
ALTER TABLE products ADD COLUMN hesabfa_manual_reserved  DECIMAL(10,2) NULL DEFAULT 0;
ALTER TABLE products ADD COLUMN hesabfa_exclude_from_sync BOOLEAN DEFAULT FALSE;
ALTER TABLE products ADD COLUMN hesabfa_stock_locked     BOOLEAN DEFAULT FALSE;
ALTER TABLE products ADD COLUMN hesabfa_stock_synced_at  TIMESTAMP NULL;
```

### ۱۰.۳. جدول `hesabfa_sync_log`

```sql
CREATE TABLE hesabfa_sync_log (
    id              BIGINT AUTO_INCREMENT PRIMARY KEY,
    order_id        BIGINT NULL,
    sync_type       VARCHAR(50) NOT NULL,  -- contact, invoice, full_sync
    status          VARCHAR(20) NOT NULL,  -- success, failed
    request_data    TEXT NULL,
    response_data   TEXT NULL,
    error_message   TEXT NULL,
    created_at      TIMESTAMP NULL,
    updated_at      TIMESTAMP NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL,
    INDEX idx_sync_log_type (sync_type),
    INDEX idx_sync_log_status (status),
    INDEX idx_sync_log_created (created_at)
);
```

---

## ۱۱. زنجیره رویدادها (Event Chain)

### ۱۱.۱. زنجیره کامل یک سفارش جدید

```
مدیر در پنل: تغییر وضعیت سفارش از pending به confirmed
         │
         ▼
1. Order::updated() fires
         │
         ├── StockReservationObserver::updated()
         │       └── reserveStock()
         │            ├── بررسی موجودی کافی
         │            └── increment hesabfa_reserved_stock
         │
         └── HesabfaObserver::updated()
                 └── syncOrder()  [فقط اگر auto_sync=true و قبلاً sync نشده]
                      ├── syncContact()
                      │    ├── GET /contact/getcontacts
                      │    └── POST /contact/save
                      │
                      └── syncInvoice()
                           ├── GET /item/getitems (برای هر محصول)
                           ├── POST /invoice/save
                           └── POST /invoice/SaveWarehouseReceipt (اختیاری)
```

**ترتیب اجرا:** هر دو Observer بعد از `save()` صدا زده می‌شوند. ترتیب بین StockReservationObserver و HesabfaObserver **تضمین شده نیست** (وابسته به ترتیب ثبت). در `AppServiceProvider.php`:
```php
Order::observe(HesabfaObserver::class);         // اول
Order::observe(StockReservationObserver::class); // دوم
```
طبق داکیومنت Laravel، Observers به ترتیب ثبت اجرا می‌شوند، پس:
1. اول `hesabfa_synced_at` روی سفارش set می‌شود (توسط HesabfaObserver)
2. بعد `hesabfa_reserved_stock` روی محصولات increment می‌شود (توسط StockReservationObserver)

این یعنی **موجودی بعد از sync شدن در حسابفا رزرو می‌شود**.

### ۱۱.۲. زنجیره Bulk Status Change

در `OrdersTable.php:194-201` یک Bulk Action برای تغییر وضعیت وجود دارد:
```php
$records->each(fn ($record) => $record->update(['status' => $data['status']]));
```
این برای هر سفارش به صورت جداگانه `update()` را صدا می‌زند که باعث می‌شود:
- برای هر سفارش یک تماس API به حسابفا انجام شود
- یک رزرو/آزادسازی انجام شود
- اگر ۵۰ سفارش انتخاب شود → ۵۰ تماس API (می‌تواند fail شود)

### ۱۱.۳. زنجیره Cron همگام‌سازی موجودی

```
هر ۶۰ دقیقه (توسط Cron)
         │
         ▼
SyncHesabfaStock::handle()
         │
         ▼
StockSyncService::syncAllStock()
         │
         ├── GET /item/GetQuantity (همه اقلام)
         └── UPDATE products SET stock_quantity (برای هر محصول)
```

### ۱۱.۴. زنجیره وب‌هوک

```
حسابفا: تغییر موجودی یا قیمت
         │
         ▼
POST /api/hesabfa/webhook {EventType: "ItemQuantityChanged", ...}
         │
         ├── بررسی secret
         ├── ذخیره در لاگ فایل
         │
         ▼
StockSyncService::updateStockByItemCode()
         │
         └── UPDATE products SET stock_quantity, hesabfa_physical_stock
```

---

## ۱۲. عوارض جانبی (Side Effects)

### ۱۲.۱. عوارض جانبی مستقیم

| عملیات | عوارض جانبی |
|--------|-------------|
| تغییر وضعیت سفارش به `confirmed` | ۱) تماس API برای sync مشتری ۲) تماس API برای sync فاکتور ۳) تماس API برای هر آیتم (جستجوی SKU) ۴) increment reservd_stock ۵) ثبت لاگ ۶) افزودن یادداشت |
| Bulk status change | تکرار موارد بالا برای هر سفارش |
| کلیک "Sync Stock" در داشبورد | ۱) دریافت تمام موجودی‌ها از حسابفا ۲) آپدیت تمام محصولات در دیتابیس |
| دریافت Webhook | ۱) آپدیت موجودی/قیمت محصول ۲) لاگ در فایل |
| ذخیره تنظیمات حسابفا | ۱) بازنویسی فایل `.env` |
| ارسال دستی به حسابفا از EditOrder | sync کامل (مشتری + فاکتور) |

### ۱۲.۲. عوارض جانبی غیرمستقیم (Cascade Effects)

| رویداد | آبشار اثرات |
|--------|------------|
| تغییر `hesabfa_manual_reserved` در پنل | تغییر `sellable_stock` → تغییر نمایش موجودی در سایت |
| تغییر `hesabfa_stock_locked` | جلوگیری از آپدیت موجودی توسط Cron و Webhook |
| حذف سفارش (soft delete) | آزادسازی موجودی رزرو شده (releaseStock) |
| عدم دسترسی به API حسابفا | شکست sync سفارش + باقی ماندن `hesabfa_synced_at = null` |
| Timeout در syncAllStock | ۱) محصولات ناقص آپدیت می‌شوند ۲) Cron بعدی هم شروع می‌شود |
| عدم تطابق SKU | کل فاکتور sync نمی‌شود (fail fast) |

### ۱۲.۳. عوارض جانبی بر روی محصولات

- `syncAllStock()` مقادیر `stock_quantity` و `hesabfa_physical_stock` را **بازنویسی** می‌کند
- `hesabfa_stock_locked` جلوی این بازنویسی را می‌گیرد
- `hesabfa_exclude_from_sync` محصول را از همگام‌سازی حذف نمی‌کند (چون `syncAllStock` از Product Model استفاده می‌کند و only sku matching) → **در واقع `hesabfa_exclude_from_sync` هیچ‌جا بررسی نمی‌شود!**

---

## ۱۳. مسائل امنیتی

### ۱۳.۱. بحرانی: ذخیره Credential در فایل .env از طریق پنل

**فایل:** `ManageSettings.php:318-354`

```php
$envContent = file_get_contents($envPath);
// ...
file_put_contents($envPath, $envContent);
```

- اگر فایل `.env` قابل نوشتن نباشد → exception
- اگر عملیات در middle of write fail شود → `.env` خراب می‌شود
- در محیط Docker معمولاً `.env` volume-mounted است و ممکن است permission مشکل داشته باشد

### ۱۳.۲. بحرانی: Webhook Secret در Query String

**فایل:** `HesabfaWebhookController.php:31-33`

```php
$providedSecret = $request->header('X-Webhook-Secret')
    ?? $request->input('secret')
    ?? $request->query('secret');
```

- Secret در query string در لاگ‌های وب‌سرور ثبت می‌شود
- در هدرهای Referer و تاریخچه مرورگر باقی می‌ماند

### ۱۳.۳. بدون Mass Assignment Protection برای فیلدهای حسابفا در Order

**فایل:** `Modules/Order/app/Models/Order.php:37-49`

فیلدهای `hesabfa_*` در `$fillable` **نیستند**. این امن است (mass assignment protection) اما در `HesabfaObserver.php:75-80` از `forceFill()` استفاده می‌شود که این حفاظت را دور می‌زند. در کد فعلی این مسئله‌ای ندارد چون فقط Observer این فیلدها را set می‌کند.

### ۱۳.۴. Permission مدیریت حسابفا

فقط نقش `admin` دسترسی `manage-hesabfa` دارد. درست است.

### ۱۳.۵. وب‌هوک بدون Rate Limiting و Throttle

هیچ محدودیتی روی `/api/hesabfa/webhook` وجود ندارد. یک مهاجم با Secret می‌تواند هزاران درخواست ارسال کند.

---

## ۱۴. مشکلات Race Condition

### ۱۴.۱. رزرو همزمان موجودی

دو درخواست همزمان برای خرید یک محصول با موجودی ۱۰:
- هر دو `hesabfa_physical_stock = 10` را می‌بینند
- هر دو `increment hesabfa_reserved_stock by 5` را اجرا می‌کنند
- نتیجه: `hesabfa_reserved_stock = 10` ✅ درست به نظر می‌رسد

اما اگر:
- درخواست ۱: `14` واحد → `14 >= 10` → fail ✅
- درخواست ۲: `6` واحد → `6 >= 10` → success (`hesabfa_physical_stock=10, hesabfa_reserved_stock=6` باقی‌مانده ۴) ✅

در MySQL با `InnoDB` و `WHERE Raw` شرط در سطح **row-level** اتمیک است. پس race condition در یک محصول وجود ندارد. اما اگر دو محصول در یک سفارش باشند و یکی fail شود، دومی همچنان commit می‌شود.

### ۱۴.۲. همگام‌سازی همزمان موجودی + وب‌هوک

اگر در حین اجرای `syncAllStock()` یک webhook بیاید:
- `syncAllStock`: `hesabfa_physical_stock = 100` را set می‌کند
- Webhook: `hesabfa_physical_stock = 95` را set می‌کند (آخرین برنده)
- **ممکن است داده وب‌هوک از دست برود** چون syncAllStock بعداً overwrite کند

### ۱۴.۳. Observer همگام‌سازی و Bulk Status Change

Bulk Action در جدول سفارشات: اگر ۱۰۰ سفارش با یک کلیک تأیید شوند:
- ۱۰۰ درخواست API به حسابفا
- ممکن است Rate Limit حسابفا فعال شود (۴۲۹)
- برخی موفق می‌شوند، برخی fail
- `hesabfa_synced_at` برای موفق‌ها set می‌شود، برای failها null می‌ماند

---

## ۱۵. مسائل و خطرات شناخته‌شده

### ۱۵.۱. بحرانی: `hesabfa_exclude_from_sync` هیچ‌جا بررسی نمی‌شود

در `Product.php` این فیلد وجود دارد و در Form نمایش داده می‌شود، اما:
- در `StockSyncService::syncAllStock()` بررسی نمی‌شود
- در `StockSyncService::updateStockByItemCode()` بررسی نمی‌شود
- فقط `excluded_skus` از config بررسی می‌شود

### ۱۵.۲. بحرانی: `enable_reserved_stock` هیچ‌جا بررسی نمی‌شود

- در `StockReservationObserver.php` استفاده نمی‌شود
- احتمالاً قرار بوده که رزرو را غیرفعال کند اما پیاده‌سازی نشده

### ۱۵.۳. زیاد: عدم لاگ موفقیت‌ها در HesabfaSyncLog

`log()` فقط برای خطاها صدا زده می‌شود. موفقیت‌ها لاگ نمی‌شوند. این یعنی:
- در داشبورد "Recent Activity" همیشه خالی یا فقط خطاها را نشان می‌دهد
- پیگیری syncهای موفق فقط از طریق `hesabfa_synced_at` ممکن است

### ۱۵.۴. زیاد: جستجوی SKU در هر بار sync

`buildProductItems()` برای هر آیتم سفارش یک تماس `findItemBySku()` به حسابفا می‌زند. اگر سفارش ۱۰ آیتم داشته باشد:
- ۱ تماس: findContactByNationalCode
- ۱ تماس: saveContact
- ۱۰ تماس: findItemBySku (یکی برای هر آیتم)
- ۱ تماس: saveInvoice
- ۱ تماس: SaveWarehouseReceipt
- **مجموع: ۱۴ تماس API برای یک سفارش**

### ۱۵.۵. زیاد: عدم Cache برای Item Codes

`findHesabfaItemCode()` هر بار از طریق API جستجو می‌کند. اگر محصولی بارها sync شود، هر بار یک تماس اضافی به حسابفا.

### ۱۵.۶. متوسط: `StockLocked` بعد از تغییر دستی بررسی نمی‌شود

اگر در پنل ادمین `stock_quantity` را دستی تغییر دهیم، Cron بعدی آن را با مقدار حسابفا overwrite می‌کند. `hesabfa_stock_locked` باید true شود تا از این اتفاق جلوگیری کند.

### ۱۵.۷. متوسط: تبدیل قیمت ریال به تومان در Webhook

```php
$priceInToman = (int) round($priceInRials / 10);
```
فرض می‌کند حسابفا قیمت را **به ریال** ارسال می‌کند. اگر حسابفا قیمت را به تومان ارسال کند، تقسیم بر ۱۰ خطای ۱۰ برابری ایجاد می‌کند.

### ۱۵.۸. متوسط: Invoice Reference Collision

فرمول `{orderId}-{floor(total/10,000,000)}` می‌تواند برای دو سفارش با یک orderId (غیرممکن) یا با مبالغ مختلف در یک محدوده ۱۰ میلیونی collision ایجاد کند. عملاً غیرمحتمل است اما طراحی ایده‌آلی نیست.

### ۱۵.۹. کم: زمانبندی Cron بدون `withoutOverlapping`

اگر `syncAllStock()` بیش از `sync_interval` طول بکشد، دو instance هم‌زمان اجرا می‌شوند.

### ۱۵.۱۰. کم: `stock_quantity` و `hesabfa_physical_stock` همیشه برابرند

در `batchUpdateStock()` هر دو به یک مقدار set می‌شوند. این یعنی `stock_quantity` عملاً تکراری و اضافی است. `hesabfa_physical_stock` برای محاسبه `sellable_stock` استفاده می‌شود و `stock_quantity` احتمالاً برای نمایش در سایت.

---

## ۱۶. نقاط ضعف در لاگینگ

| جنبه | وضعیت فعلی | ریسک |
|------|-----------|------|
| موفقیت syncOrder | لاگ نمی‌شود | ناتوانی در گزارش‌گیری از syncهای موفق |
| موفقیت syncAllStock | لاگ نمی‌شود | عدم امکان پیگیری تاریخچه موجودی |
| تغییر `hesabfa_stock_locked` | لاگ نمی‌شود | عدم پیگیری تغییرات امنیتی |
| تغییر `hesabfa_manual_reserved` | لاگ نمی‌شود | عدم پیگیری تغییرات دستی موجودی |
| درخواست‌های API (HesabfaService) | لاگ سطح DEBUG در hesabfa.log | در production ممکن است DEBUG خاموش باشد |
| Webhook payload | لاگ در فایل مجزا | خوب است |
| خطاهای API | لاگ ERROR در hesabfa.log | خوب است |
| لاگ credential | در sanitized payload حذف می‌شود | خوب است |

**نتیجه:** سیستم لاگینگ برای خطاها مناسب است اما برای ردیابی موفقیت‌ها ناقص است.

---

## ۱۷. خلاصه مشکلات بحرانی

| # | مشکل | سطح | فایل | راهکار پیشنهادی |
|---|------|------|------|----------------|
| ۱ | `hesabfa_exclude_from_sync` بررسی نمی‌شود | **B-Blocker** | `StockSyncService.php` | اضافه کردن شرط در `batchUpdateStock()` |
| ۲ | `enable_reserved_stock` بررسی نمی‌شود | **B-Blocker** | `StockReservationObserver.php` | اگر false باشد، reserve/release نادیده گرفته شود |
| ۳ | عدم لاگ موفقیت sync | **Major** | `HesabfaObserver.php` | افزودن `log()` در مسیر success |
| ۴ | `.env` مستقیم در production بازنویسی می‌شود | **Major** | `ManageSettings.php` | استفاده از دیتابیس برای تنظیمات یا اعتبارسنجی دسترسی |
| ۵ | Webhook secret در query string قابل دریافت است | **Major** | `HesabfaWebhookController.php` | حذف `$request->query('secret')` |
| ۶ | SKU جستجو در هر بار sync (performance) | **Minor** | `HesabfaObserver.php` | افزودن Cache برای mapping SKU→ItemCode |
| ۷ | تبدیل ریال به تومان در Webhook بدون تأیید واحد | **Major** | `StockSyncService.php` | افزودن بررسی واحد با AccountInfo API |
| ۸ | Cron بدون `withoutOverlapping()` | **Minor** | `AppServiceProvider.php` | افزودن `->withoutOverlapping()` |
| ۹ | عدم Transaction در عملیات‌های چندمرحله‌ای | **Major** | چند فایل | استفاده از `DB::transaction()` |
| ۱۰ | هیچ تستی برای Hesabfa وجود ندارد | **Major** | - | افزودن Feature tests |

---

## پیوست: نمودار جریان کامل داده

```
حسابفا (Hesabfa Cloud)
    │
    ├── API (با HesabfaService)
    │    ├── contact/getcontacts ← برای جستجوی مشتری
    │    ├── contact/save ← برای ذخیره مشتری
    │    ├── item/getitems ← برای جستجوی SKU
    │    ├── item/GetQuantity ← برای دریافت موجودی
    │    ├── invoice/save ← برای ذخیره فاکتور
    │    └── invoice/SaveWarehouseReceipt ← برای رسید انبار
    │
    ├── Webhook → POST /api/hesabfa/webhook
    │    ├── ItemQuantityChanged → StockSyncService::updateStockByItemCode()
    │    └── ItemPriceChanged → StockSyncService::updatePriceByItemCode()
    │
    └── (Cron همگام‌سازی) → SyncHesabfaStock
         └── StockSyncService::syncAllStock()
              └── GET /item/GetQuantity → batchUpdate products

بانک اطلاعاتی (MySQL)
    │
    ├── orders.hesabfa_* (4 ستون)
    ├── products.hesabfa_* (6 ستون)
    └── hesabfa_sync_log (جدول لاگ)

پنل ادمین (Filament)
    │
    ├── HesabfaDashboard → آمار و دکمه Sync
    ├── HesabfaSyncLogs → مشاهده لاگ‌ها
    ├── ManageSettings → تنظیمات حسابفا (نوشتن در .env)
    ├── EditOrder → دکمه ارسال دستی به حسابفا
    └── ProductForm → مشاهده موجودی حسابفا
```

**تعداد کل فایل‌های مرتبط:** ۳۶+ فایل  
**تعداد خطوط کد تقریبی:** ~۲۵۰۰ خط  
**تعداد API endpoints مصرف شده:** ۹  
**تعداد تست‌های موجود:** ۰
