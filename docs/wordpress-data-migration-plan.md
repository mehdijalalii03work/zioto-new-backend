# طرح مهاجرت دیتا از وردپرس به لاراول (زیوتو)

## فهرست
1. [بررسی اجمالی دیتابیس‌ها](#1-بررسی-اجمالی-دیتابیس‌ها)
2. [آنالیز تطابق جداول](#2-آنالیز-تطابق-جداول)
3. [استراتژی مهاجرت](#3-استراتژی-مهاجرت)
4. [مراحل اجرا (گام به گام)](#4-مراحل-اجرا-گام-به-گام)
5. [اسکریپت‌های مورد نیاز](#5-اسکریپت‌های-مورد-نیاز)
6. [سناریوی تست روی Stage](#6-سناریوی-تست-روی-stage)
7. [اجرای نهایی روی Production](#7-اجرای-نهایی-روی-production)
8. [ریسک‌ها و راهکارها](#8-ریسک‌ها-و-راهکارها)

---

## 1. بررسی اجمالی دیتابیس‌ها

### دیتابیس وردپرس (فایل بکاپ)
| جدول | تعداد رکورد | توضیح |
|---|---|---|
| `wp_users` | 4,562 | کاربران |
| `wp_usermeta` | ~140,974 | متادیتای کاربران شامل تلفن، کد ملی، آدرس‌ها |
| `wp_wc_orders` | 3,904 | سفارش‌های ووکامرس |
| `wp_wc_order_stats` | 2,760 | آمار سفارش‌ها |
| `wp_wc_order_addresses` | 5,967 | آدرس‌های سفارش (billing + shipping) |
| `wp_wc_orders_meta` | 33,051 | متادیتای سفارش‌ها |
| `wp_woocommerce_order_items` | 15,730 | آیتم‌های سفارش |
| `wp_woocommerce_order_itemmeta` | 112,486 | متادیتای آیتم‌های سفارش |
| `wp_pec_payments` | 2,943 | پرداخت‌های درگاه پاسارگاد |
| `wp_postmeta` | 210,876 | متادیتای محصولات |
| `wp_posts` (product) | 45 | محصولات |
| `wp_comments` | 14,706 | نظرات |
| `wp_zioto_hesabfa_sync_log` | 1,609 | لاگ همگام‌سازی حسابفا |
| `wp_zioto_sms_log` | 5 | لاگ پیامکی |

**وضعیت سفارش‌های وردپرس:**
| وضعیت | تعداد |
|---|---|
| wc-cancelled | 2,391 |
| wc-completed | 1,448 |
| wc-processing | 39 |
| wc-failed | 23 |
| wc-packing | 1 |
| wc-delivered | 1 |
| auto-draft | 1 |
| **فعال (غیر cancelled)** | **~1,489** |

**درگاه‌های پرداخت وردپرس:**
| روش | تعداد |
|---|---|
| WC_Pec_Gateway (پاسارگاد) | 2,342 |
| WCDigiPay (دیجی‌پی - اقساطی) | 1,430 |
| kamanlend (کمان‌لند - اقساطی) | 59 |
| wc_smartis_gateway (اسمارتیس - اقساطی) | 47 |
| نامشخص | 26 |

### دیتابیس لاراول (Stage)
| جدول | تعداد |
|---|---|
| `users` | 264 |
| `user_addresses` | 146 |
| `orders` | 323 |
| `order_items` | 448 |
| `payments` | 321 |
| `products` | 41 |
| `carts` | 188 |
| `wishlists` | 1 |

**بازه زمانی:** وردپرس از اسفند 1400 تا مرداد 1405. لاراول دیتای جدید بعد از راه‌اندازی.

---

## 2. آنالیز تطابق جداول

### 2.1 کاربران
| وردپرس | لاراول | توضیح |
|---|---|---|
| `wp_users.ID` | (نیاز به mapping در جدول موقت) | بدون تغییر در schema لاراول |
| `wp_users.user_email` | `users.email` | (بسیاری خالی) |
| `display_name` | `users.name` | مستقیم |
| `first_name` از wp_usermeta | `users.first_name` | |
| `last_name` از wp_usermeta | `users.last_name` | |
| `billing_phone` از wp_usermeta | `users.phone` | **کلید تطابق** |
| `national_code` از wp_usermeta | `users.national_code` | |
| `shahkar_verified` از wp_usermeta | `users.shahkar_verified` | |
| `user_registered` | `users.created_at` | |

### 2.2 سفارشات
- **status mapping:**
  - `wc-completed` → `confirmed`
  - `wc-processing` → `confirmed`
  - `wc-packing` → `processing`
  - `wc-delivered` → `delivered`
  - `wc-cancelled` → `cancelled`
  - `wc-failed` → `cancelled`

### 2.3 gateway → payment_method mapping
| وردپرس | gateway در لاراول | payment_method |
|---|---|---|
| `WC_Pec_Gateway` | `pec` | `online` |
| `WCDigiPay` | `digipay` | `installment` |
| `kamanlend` | `kamanlend` | `installment` |
| `wc_smartis_gateway` | `smartis` | `installment` |

> نکته: WCDigiPay، kamanlend و wc_smartis_gateway در وردپرس به عنوان درگاه‌های خرید اقساطی استفاده شده‌اند. فقط WC_Pec_Gateway پرداخت آنلاین (online) بوده است.

---

## 3. استراتژی مهاجرت

### تصمیمات کلیدی
1. **محصولات:** تطابق با SKU. هیچ محصول جدیدی در لاراول ساخته نمی‌شود.
2. **دیتای جدید لاراول (264 کاربر + 323 سفارش):** **حفظ شود**. دیتای وردپرس به آن اضافه می‌شود.
3. **کاربران تکراری:** تطابق با `phone`. کاربران موجود آپدیت نمی‌شوند.
4. **بدون تغییر schema لاراول:** نیازی به اضافه کردن `wp_user_id` به جدول `users` نیست. mapping کاربران و سفارش‌ها در جداول موقت دیتابیس `wp_data` نگهداری می‌شود.
5. **order_number:** سفارش‌های وردپرس از 21324 به بعد شماره‌گذاری شوند.

### ترتیب مهاجرت
```
1. محصولات            ← فقط mapping (product_sku_map با SKU)
2. کاربران             ← import + شناسایی تکراری با phone
3. آدرس کاربران        ← تبدیل از usermeta billing_*
4. سفارش‌ها            ← با لینک به کاربران
5. آیتم سفارش          ← لینک به سفارش و محصول (با SKU)
6. پرداخت‌ها            ← لینک به سفارش
7. لاگ حسابفا          ← لینک به سفارش
```

---

## 4. مراحل اجرا

### پیش‌نیازها
```bash
# روی Stage
ssh root@194.5.188.212
mysql -u root -pstagepass123 -e "CREATE DATABASE IF NOT EXISTS wp_data;"
mysql -u root -pstagepass123 wp_data < /root/sawiss-wp-backup-1405-05-06.sql

# بکاپ stage
mysqldump -u root -pstagepass123 zioto_stage > /root/zioto_stage_before_migration.sql
```

### بدون تغییر در Schema لاراول
نیازی به اضافه کردن فیلد جدید نیست. تمام mapping های لازم (wp_user_id → laravel_user_id, wp_order_id → laravel_order_id, wp_product_id → laravel_product_id) در دیتابیس کمکی `wp_data` در جداول موقت ذخیره می‌شوند.

### Connection wp_data در config/database.php
```php
'wp_data' => [
    'driver' => 'mysql',
    'host' => env('WP_DB_HOST', '127.0.0.1'),
    'port' => env('WP_DB_PORT', '3306'),
    'database' => env('WP_DB_DATABASE', 'wp_data'),
    'username' => env('WP_DB_USERNAME', 'root'),
    'password' => env('WP_DB_PASSWORD', ''),
    'charset' => 'utf8mb4',
    'prefix' => 'wp_',
],
```

### اسکریپت محصولات (ProductSkuMap)
روش تطبیق: **SKU** (تنها روش - چون هم در وردپرس و هم در لاراول محصولات SKU یکسان دارند و این قابل اعتمادترین روش است)

```sql
CREATE TABLE wp_data.product_sku_map (
    wp_product_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
    laravel_product_id BIGINT UNSIGNED NOT NULL,
    sku VARCHAR(255) NOT NULL,
    UNIQUE KEY (laravel_product_id)
);

INSERT INTO wp_data.product_sku_map (wp_product_id, laravel_product_id, sku)
SELECT p.ID, lp.id, sku_meta.meta_value
FROM wp_data.wp_posts p
JOIN wp_data.wp_postmeta sku_meta ON p.ID = sku_meta.post_id AND sku_meta.meta_key = '_sku'
JOIN zioto_stage.products lp ON lp.sku = sku_meta.meta_value
WHERE p.post_type = 'product';
```

محصولاتی از وردپرس که SKUشان در لاراول وجود ندارد: **نادیده گرفته می‌شوند** (هیچ محصول جدیدی ساخته نمی‌شود).

### اسکریپت کاربران (پیشنهادی PHP)
```php
// WpMigration/ImportUsers.php
// بدون نیاز به wp_user_id در جدول users. mapping در wp_data.user_mapping ذخیره می‌شود.
$wp = DB::connection('wp_data');
$wp->table('users')->orderBy('ID')->chunk(100, function ($users) {
    foreach ($users as $wpUser) {
        $phone = $this->getMeta($wpUser->ID, 'billing_phone');
        if (!$phone) continue;
        if (User::where('phone', $phone)->exists()) {
            // فقط mapping را در wp_data.user_mapping ذخیره کن
            $this->saveMapping($wpUser->ID, $existing->id);
            continue;
        }
        $user = User::create([
            'name' => $wpUser->display_name,
            'first_name' => $this->getMeta($wpUser->ID, 'first_name'),
            'last_name' => $this->getMeta($wpUser->ID, 'last_name'),
            'email' => $wpUser->user_email ?: null,
            'phone' => $phone,
            'national_code' => $this->getMeta($wpUser->ID, 'national_code'),
            'shahkar_verified' => $this->getMeta($wpUser->ID, 'shahkar_verified') === '1',
            'password' => bcrypt(Str::random(32)),
            'created_at' => $wpUser->user_registered,
        ]);
        $this->saveMapping($wpUser->ID, $user->id);
    }
});
```

### ساختار پیشنهادی
```
app/Console/Commands/WpMigration/
├── MapProducts.php
├── ImportUsers.php
├── ImportUserAddresses.php
├── ImportOrders.php
├── ImportOrderItems.php
├── ImportPayments.php
└── ImportHesabfaLogs.php

app/Services/WpMigration/
├── WpDatabase.php
├── UserMigrator.php
├── OrderMigrator.php
├── PaymentMigrator.php
└── ProductMapper.php
```

### کامندهای Artisan
```bash
php artisan migrate:wp-products-map       # ایجاد product_sku_map بر اساس SKU
php artisan migrate:wp-users --dry-run    # فقط آمار
php artisan migrate:wp-users
php artisan migrate:wp-user-addresses
php artisan migrate:wp-orders --dry-run
php artisan migrate:wp-orders
php artisan migrate:wp-order-items
php artisan migrate:wp-payments
php artisan migrate:wp-hesabfa-logs
php artisan migrate:wp-verify
```

---

## 5. سناریوی تست Stage

```sql
-- بررسی تعداد و صحت کاربران
SELECT COUNT(*) FROM users;
SELECT phone, COUNT(*) FROM users GROUP BY phone HAVING COUNT(*) > 1;

-- بررسی سفارش‌ها (قدیمی + جدید)
SELECT MIN(order_number), MAX(order_number) FROM orders;
SELECT COUNT(*) FROM orders WHERE user_id IS NULL;

-- بررسی payment_method
SELECT DISTINCT payment_method, COUNT(*) FROM payments GROUP BY payment_method;

-- بررسی gateway
SELECT DISTINCT gateway, COUNT(*) FROM payments GROUP BY gateway;
```

---

## 6. اجرای Production

### پیش‌نیاز
- کد PHP از `stage-backend` به `production-backend` sync شده باشد (همه فایل‌های `app/Console/Commands/WpMigration/` و `app/Services/WpMigration/`)
- فایل migration `*_make_province_and_city_nullable_in_user_addresses.php` اجرا شده باشد
- Connection `wp_data` در `config/database.php` active باشد

### چک‌لیست مرحله به مرحله

```bash
# ========== مرحله 0: بکاپ کامل قبل از هر چیزی ==========
mysqldump -u root -p"$PASS" --all-databases > /root/full_backup_before_migration.sql
rsync -avz /var/www/html/storage/ /root/storage_backup/

# ========== مرحله 1: Maintenance mode ==========
php artisan down --retry=60
mysqldump -u root -p"$PASS" zioto_db > /root/zioto_final_backup.sql

# ========== مرحله 2: Import دیتابیس وردپرس ==========
mysql -u root -p"$PASS" -e "CREATE DATABASE IF NOT EXISTS wp_data;"
mysql -u root -p"$PASS" wp_data < /root/sawiss-wp-backup-1405-05-06.sql

# ========== مرحله 3: اجرای مهاجرت ==========

# 3a. ابتدا شهرها (بانک کامل ایران)
php artisan migrate:wp-cities --dry-run
php artisan migrate:wp-cities

# 3b. محصولات (فقط mapping با SKU)
php artisan migrate:wp-products-map

# 3c. کاربران
php artisan migrate:wp-users --dry-run
php artisan migrate:wp-users

# 3d. آدرس کاربران
php artisan migrate:wp-user-addresses

# 3e. سفارش‌ها
php artisan migrate:wp-orders --dry-run
php artisan migrate:wp-orders

# 3f. آیتم سفارش
php artisan migrate:wp-order-items

# 3g. پرداخت‌ها
php artisan migrate:wp-payments

# 3h. لاگ حسابفا
php artisan migrate:wp-hesabfa-logs

# 3i. گزارش نهایی
php artisan migrate:wp-verify

# ========== مرحله 4: Verification ==========
mysql -u root -p"$PASS" zioto_db -e "
SELECT 'users', COUNT(*) FROM users
UNION ALL SELECT 'user_addresses', COUNT(*) FROM user_addresses
UNION ALL SELECT 'orders', COUNT(*) FROM orders
UNION ALL SELECT 'order_items', COUNT(*) FROM order_items
UNION ALL SELECT 'payments', COUNT(*) FROM payments
UNION ALL SELECT 'cities', COUNT(*) FROM cities;
"

# ========== مرحله 5: خروج از maintenance ==========
php artisan up
php artisan optimize:clear
```

### نکات مهم بعد از production
- کاربران با OTP (SMS) وارد می‌شوند — پسورد placeholder random hash است
- `php artisan hesabfa:sync-stock` برای بروزرسانی موجودی در حسابفا

---

## 7. ریسک‌ها و راهکارها

| ریسک | احتمال | راهکار |
|---|---|---|
| **شماره تلفن تکراری** | زیاد | تطابق با `phone` و ذخیره mapping در جدول موقت. کاربر تکراری ساخته نمی‌شود |
| **SKU محصول در لاراول یافت نشد** | متوسط | (ایمن - فقط محصولاتی که SKUشان در لاراول است mapping می‌شوند) محصول بدون تطابق نادیده گرفته می‌شود |
| **عدم تطابق استان/شهر** | زیاد | تطابق با slug فارسی در `provinces` و `cities` موجود در لاراول |
| **gateway_response JSON نامعتبر** | کم | ذخیره null در صورت invalid JSON |
| **تداخل order_number** | کم | شروع سفارش‌های وردپرس از 21324 (بعد از آخرین شماره فعلی لاراول) |
| **محدودیت حافظه** | کم | استفاده از `chunk()` در Eloquent برای پردازش bulk |
| **وضعیت پرداخت نامشخص** | متوسط | اگر سفارش `wc-completed` است و transaction_id دارد = `paid`؛ در غیر این صورت وضعیت از wp_pec_payments تطبیق داده شود |
| **Hesabfa sync conflict** | متوسط | `hesabfa_synced_at` از متا سفارش وردپرس حفظ شود تا sync مجدد انجام نشود |
| **پسورد کاربران** | حتمی | کاربران با OTP (SMS) وارد می‌شوند. پسورد placeholder (random hash) ذخیره شود |

### نکات اضافی
- **تغییر schema:** نیازی به اضافه کردن فیلد جدید به جداول لاراول نیست. تمام mapping در جداول موقت `wp_data` ذخیره می‌شود.
- **محصول جدید:** هیچ محصول جدیدی در لاراول ساخته نمی‌شود. فقط mapping بین SKUهای یکسان انجام می‌شود.
- **تصاویر:** در این plan لحاظ نشده. پروژه جداگانه برای انتقال به Spatie Media Library.
- **Session:** کاربران با شماره تلفن + OTP وارد می‌شوند (سیستم فعلی لاراول).

---

## 8. آمار نهایی (پیش‌بینی)

| موجودیت | وردپرس | لاراول فعلی | بعد مهاجرت |
|---|---|---|---|
| کاربران | 4,562 | 264 | ~4,600 (با احتساب تکراری‌های phone) |
| سفارش فعال | ~1,489 | 78 | ~1,567 |
| سفارش کنسل | ~2,391 | 245 | ~2,636 |
| مجموع سفارش | 3,904 | 323 | 4,227 |
| آیتم سفارش | 15,730 | 448 | 16,178 |
| پرداخت | 2,943 | 321 | 3,264 |
| محصول | 45 | 41 | 41 (هیچ محصول جدیدی ساخته نمی‌شود) |

---

**نویسنده:** AI Assistant  
**تاریخ:** 1405-05-07 (July 28, 2026)  
**مسیر:** `docs/wordpress-data-migration-plan.md`
