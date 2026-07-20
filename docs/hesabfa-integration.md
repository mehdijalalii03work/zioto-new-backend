# بررسی کامل ادغام حسابفا در پروژه

## ۱. معماری کلی

ادغام حسابفا در پروژه از ۴ لایه اصلی تشکیل شده:

| لایه | فایل‌ها | نقش |
|------|---------|------|
| **API Client** | `HesabfaService.php` | ارتباط خام با API حسابفا |
| **Business Logic** | `StockSyncService.php` | همگام‌سازی موجودی |
| | `HesabfaObserver.php` | ارسال خودکار سفارشات |
| | `StockReservationObserver.php` | رزرو موجودی |
| **Scheduler** | `SyncHesabfaStock.php` | کنسول Artisan |
| | `AppServiceProvider.php` | زمان‌بندی Cron |
| **UI** | `HesabfaDashboard.php` | داشبورد + دکمه سنک |
| | `HesabfaSyncLogs.php` | لاگ همگام‌سازی |

---

## ۲. جریان همگام‌سازی موجودی (Stock Sync)

```
هر ۶۰ دقیقه (cron) یا دستی از داشبورد
        │
        ▼
SyncHesabfaStock Artisan command
        │
        ▼
StockSyncService::syncAllStock()
        │
        ├── ۱) بررسی تنظیمات (API key, login token, sync_stock=true)
        │
        ├── ۲) getAllItemQuantities() ──► GET /item/GetQuantity
        │      همه اقلام انبار ۱۱ حسابفا را برمی‌گرداند
        │
        ├── ۳) prepareStockUpdates() ──► فیلتر و نرمال‌سازی
        │      - فیلتر SKU های excluded
        │      - استخراج ItemCode/ProductCode → SKU
        │      - استخراج Quantity/Physical → تعداد
        │      - حذف تکراری‌ها
        │
        └── ۴) batchUpdateStock()
               - Product::whereIn('sku', $skus) ──► پیدا کردن محصولات موجود
               - برای هر SKU: $product->update([
                   stock_quantity,
                   hesabfa_physical_stock,
                   hesabfa_stock_synced_at
                 ])
```

---

## ۳. جریان ارسال سفارش به حسابفا (Order Sync)

```
Order::updated() event
        │
        ▼
HesabfaObserver::updated()
        │
        ├── بررسی: auto_sync فعال؟
        ├── بررسی: وضعیت سفارش confirmed/processing؟
        ├── بررسی: قبلاً sync نشده؟
        │
        ▼
syncOrder()
        │
        ├── syncContact() ──► پیدا کردن/ساختن مشتری با کد ملی
        │      ┌─ findContactByNationalCode() ◄── GET /contact/getcontacts
        │      └─ saveContact() ◄── POST /contact/save
        │
        └── syncInvoice() ──► ساخت فاکتور
               ├── buildInvoiceItems()
               │      ├── محصولات (itemCode از SKU)
               │      ├── حمل و نقل (اگر هزینه > 0)
               │      └── کارمزد اقساطی (اگر روش پرداخت installment)
               │
               ├── saveInvoice() ◄── POST /invoice/save
               └── saveWarehouseReceipt() ◄── POST /invoice/SaveWarehouseReceipt
```

**فرمول مرجع فاکتور:** `{orderId}-{floor(totalInRials / 10,000,000)}`

---

## ۴. جریان رزرو موجودی (Stock Reservation)

```
Order::created / updated / deleted
        │
        ▼
StockReservationObserver
        │
        ├── ایجاد سفارش با status confirmed/processing → reserveStock()
        ├── تغییر status به confirmed/processing → reserveStock()
        ├── تغییر status از confirmed/processing → releaseStock()
        └── حذف سفارش با status confirmed/processing → releaseStock()
```

**فرمول رزرو:** `sellable_stock = hesabfa_physical_stock - hesabfa_reserved_stock - hesabfa_manual_reserved`

---

## ۵. ساختار دیتابیس

### جدول `products` (ستون‌های مرتبط با حسابفا)

| ستون | نوع | Nullable | توضیح |
|------|------|----------|-------|
| `sku` | string | — | کد محصول، کلید اتصال به حسابفا |
| `stock_quantity` | integer | — | موجودی (سنک شده از حسابفا) |
| `hesabfa_physical_stock` | decimal(10,2) | ✅ | موجودی فیزیکی انبار حسابفا |
| `hesabfa_reserved_stock` | decimal(10,2) | ✅ | رزرو شده توسط سفارشات |
| `hesabfa_manual_reserved` | decimal(10,2) | ✅ (default: 0) | رزرو دستی |
| `hesabfa_exclude_from_sync` | boolean | — (default: false) | غیرفعال کردن سنک |
| `hesabfa_stock_locked` | boolean | — (default: false) | قفل موجودی |
| `hesabfa_stock_synced_at` | timestamp | ✅ | آخرین زمان سنک |

### جدول `orders` (ستون‌های مرتبط با حسابفا)

| ستون | نوع | Nullable | توضیح |
|------|------|----------|-------|
| `hesabfa_contact_code` | string(50) | ✅ | کد مشتری در حسابفا |
| `hesabfa_invoice_number` | bigint | ✅ | شماره فاکتور حسابفا |
| `hesabfa_invoice_reference` | string(50) | ✅ | مرجع فاکتور |
| `hesabfa_synced_at` | timestamp | ✅ | زمان ارسال به حسابفا |

### جدول `hesabfa_sync_log`

| ستون | نوع | توضیح |
|------|------|-------|
| `order_id` | FK (nullable) | سفارش مرتبط |
| `sync_type` | string(50) | contact / invoice / full_sync |
| `status` | string(20) | success / failed |
| `request_data` | text (JSON) | درخواست ارسالی |
| `response_data` | text (JSON) | پاسخ دریافتی |
| `error_message` | text | پیام خطا |

---

## ۶. نقاط قوت و ضعف

### نقاط قوت

- **جدا بودن لایه‌ها:** `HesabfaService` فقط API می‌زند، `StockSyncService` منطق کسب‌وکار، `Observer` رویدادها
- **لاگ فقط خطاها:** از بزرگ شدن دیتابیس جلوگیری می‌کند (طبق درخواست کاربر)
- **رزرو موجودی جداگانه:** `StockReservationObserver` مستقل از `HesabfaObserver` عمل می‌کند
- **فیلتر SKU:** امکان исключение محصولات خاص از سنک

### نقاط ضعف و بهبودها

1. **عدم وجود `chunk()` در سنک:** اگر تعداد محصولات زیاد باشد، همه در حافظه لود می‌شوند. بهتر است `chunk(100)` استفاده شود.

2. **عدم وجود transaction:** اگر آپدیت یک محصول خطا بدهد، بقیه همچنان آپدیت می‌شوند. برای حالت عادی قابل قبول است ولی برای عملیات حجیم بهتر است `DB::transaction()` استفاده شود.

3. **عدم وجود retry:** اگر API حسابفا موقتاً در دسترس نباشد، هیچ مکانیزم تلاش مجددی وجود ندارد.

4. **عدم سنک قیمت:** `StockSyncService` فقط موجودی سنک می‌کند. قیمت فقط از طریق webhook قابل آپدیت است (`updatePriceByItemCode`).

5. **عدم وجود validation برای SKU:** اگر SKU در حسابفا تغییر کند، محصول مچ نمی‌شود و سکوت نادیده گرفته می‌شود.

6. **`hesabfa_stock_locked` هرگز بررسی نمی‌شود:** در `StockSyncService` وجود دارد ولی هیچ‌جا چک نمی‌شود. اگر قصد دارید قفل موجودی کار کند، باید در `batchUpdateStock` بررسی شود.

---

## ۷. تنظیمات (config/hesabfa.php)

| کلید | env | مقدار پیش‌فرض | توضیح |
|------|-----|---------------|-------|
| `api_key` | `HESABFA_API_KEY` | — | کلید API |
| `login_token` | `HESABFA_LOGIN_TOKEN` | — | توکن ورود |
| `base_url` | `HESABFA_BASE_URL` | `https://api.hesabfa.com/v1` | آدرس API |
| `default_project` | `HESABFA_DEFAULT_PROJECT` | `سایت ZIOTO` | پروژه پیش‌فرض |
| `warehouse_code` | `HESABFA_WAREHOUSE_CODE` | `11` | کد انبار |
| `auto_sync` | `HESABFA_AUTO_SYNC` | `true` | سنک خودکار سفارشات |
| `sync_stock` | `HESABFA_SYNC_STOCK` | `true` | سنک موجودی |
| `sync_interval` | `HESABFA_SYNC_INTERVAL` | `60` (دقیقه) | فاصله زمانی سنک |
| `excluded_skus` | `HESABFA_EXCLUDED_SKUS` | — | SKU های исключение شده |
| `enable_warehouse_receipt` | `HESABFA_ENABLE_WAREHOUSE_RECEIPT` | `false` | رسید انبار |
| `webhook_secret` | `HESABFA_WEBHOOK_SECRET` | — | رمز webhook |

---

## ۸. API های استفاده شده

| endpoint | method | کاربرد |
|----------|--------|--------|
| `/contact/getcontacts` | POST | جستجوی مشتری |
| `/contact/save` | POST | ذخیره/آپدیت مشتری |
| `/item/get` | POST | دریافت اطلاعات یک قلم |
| `/item/getitems` | POST | جستجوی اقلام |
| `/item/GetQuantity` | POST | دریافت موجودی همه اقلام |
| `/invoice/save` | POST | ذخیره فاکتور |
| `/invoice/SaveWarehouseReceipt` | POST | ثبت رسید انبار |
| `/invoice/getinvoices` | POST | جستجوی فاکتورها |
| `/invoice/get` | POST | دریافت جزئیات فاکتور |

**نکته مهم:** همه درخواست‌ها POST هستند و `apiKey` + `loginToken` در body ارسال می‌شوند (نه در header).

---

## ۹. فایل‌های مرتبط

```
app/
├── Console/Commands/
│   └── SyncHesabfaStock.php          # Artisan command
├── Filament/Pages/Hesabfa/
│   ├── HesabfaDashboard.php          # داشبورد اصلی
│   └── HesabfaSyncLogs.php           # لاگ همگام‌سازی
├── Models/
│   └── HesabfaSyncLog.php            # مدل لاگ
├── Observers/
│   ├── HesabfaObserver.php           # سنک سفارشات
│   └── StockReservationObserver.php  # رزرو موجودی
├── Providers/
│   └── AppServiceProvider.php        # زمان‌بندی Cron
└── Services/
    ├── HesabfaService.php            # API Client
    └── StockSyncService.php          # سنک موجودی

config/
└── hesabfa.php                       # تنظیمات

database/migrations/
├── 2026_06_22_104051_add_hesabfa_fields_to_orders_table.php
├── 2026_06_22_104052_create_hesabfa_sync_log_table.php
└── 2026_06_22_113438_add_hesabfa_fields_to_products_table.php

resources/views/filament/pages/
├── hesabfa-dashboard.blade.php       # ویو داشبورد
└── hesabfa-sync-logs.blade.php       # ویو لاگ
```
