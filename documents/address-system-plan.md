# سامانه مدیریت آدرس‌های کاربران
**پروژه:** فروشگاه اینترنتی طلا و نقره Zioto
**تاریخ:** ۱۴۰۵/۰۴/۰۱

---

## ۱. وضعیت فعلی

| بخش | وضعیت |
|---|---|
| جدول آدرس | **وجود ندارد** |
| مدل `Address` | **وجود ندارد** |
| `shipping_address` در سفارش | فیلد `TEXT` ساده بدون ساختار |
| فرم تسویه حساب | **هیچ فیلد آدرسی جمع‌آوری نمی‌کند** |
| پروفایل کاربر | قابلیت مدیریت آدرس ندارد |
| اعتبارسنجی آدرس | وجود ندارد |

---

## ۲. ساختار دیتابیس

### ۲.۱. جدول `provinces`

| فیلد | نوع | توضیحات |
|------|-----|---------|
| `id` | tinyint PK | |
| `name` | varchar(50) | نام استان (فارسی) |
| `slug` | varchar(50) unique | شناسه URL |
| `timestamps` | | |

**داده:** ۳۱ استان ایران، بارگذاری با Seeder از فایل JSON.

### ۲.۲. جدول `cities`

| فیلد | نوع | توضیحات |
|------|-----|---------|
| `id` | int PK | |
| `province_id` | tinyint FK→provinces | استان |
| `name` | varchar(100) | نام شهر (فارسی) |
| `slug` | varchar(100) | شناسه URL |
| `timestamps` | | |

**داده:** حدود ۵۰۰+ شهر ایران، با Seeder از فایل JSON.

### ۲.۳. جدول `user_addresses`

| فیلد | نوع                                   | توضیحات                              |
|------|---------------------------------------|--------------------------------------|
| `id` | bigint PK                             |                                      |
| `user_id` | bigint FK→users(id) ON DELETE CASCADE | مالک آدرس                            |
| `label` | varchar(50) nullable                  | عنوان نمایشی: خانه، محل کار، ...     |
| `province_id` | tinyint FK→provinces                  | استان                                |
| `city_id` | int FK→cities                         | شهر                                  |
| `district` | varchar(100) nullable                 | منطقه / محله                         |
| `postal_code` | varchar(20) nullable                  | کد پستی ۱۰ رقمی                      |
| `address_line` | text                                  | آدرس کامل (خیابان، کوچه، پلاک، طبقه) |
| `receiver_name` | varchar(100) nullable                 | نام تحویل‌گیرنده (برای هدیه)         |
| `receiver_phone` | varchar(20) nullable                  | شماره تحویل‌گیرنده                   |
| `receiver_national_code` | varchar(10) nullable                  | کدملی تحویل‌گیرنده                   |
| `latitude` | decimal(10,7) nullable                | مختصات (ارسال پیک)                   |
| `longitude` | decimal(10,7) nullable                |                                      |
| `is_default` | boolean default false                 | آدرس پیش‌فرض ارسال                   |
| `is_billing` | boolean default false                 | آدرس صورتحساب                        |
| `timestamps` |                                       |                                      |
| `deleted_at` | timestamp nullable                    | Soft Delete                          |

**ایندکس‌ها:** `(user_id)`، `(user_id, is_default)`، `(province_id, city_id)`

---

## ۳. مدل‌ها و ارتباطات

```
User
  ├── hasMany → UserAddress
  └── hasOne (via UserAddress where is_default=1) → defaultAddress

UserAddress
  ├── belongsTo → User
  ├── belongsTo → Province
  ├── belongsTo → City
  ├── scope: default() - آدرس پیش‌فرض
  ├── scope: billing() - آدرس صورتحساب
  └── accessor: full_address - نمایش کامل (استان، شهر، آدرس، کد پستی)

Province
  ├── hasMany → City
  └── scope: ordered() - مرتب‌سازی الفبایی

City
  └── belongsTo → Province

Order
  ├── belongsTo → UserAddress (nullable) - FK برای ارجاع
  └── shipping_address_snapshot (text nullable) - متن کامل آدرس در زمان ثبت
```

> **استراتژی ذخیره‌سازی در سفارش:** هم `user_address_id` (FK) و هم `shipping_address_snapshot` (snapshot از متن کامل آدرس در لحظه ثبت) نگهداری می‌شود تا ویرایش بعدی آدرس توسط کاربر روی سفارش‌های قبلی تأثیر نگذارد.

---

## ۴. API‌ها

### ۴.۱. مدیریت آدرس‌ها (Profile)

| متد | مسیر | توضیحات |
|-----|------|---------|
| GET | `/api/addresses` | لیست آدرس‌های کاربر جاری |
| POST | `/api/addresses` | ایجاد آدرس جدید |
| GET | `/api/addresses/{id}` | جزئیات یک آدرس |
| PUT | `/api/addresses/{id}` | ویرایش آدرس |
| DELETE | `/api/addresses/{id}` | حذف آدرس (Soft Delete) |
| PUT | `/api/addresses/{id}/default` | تنظیم به عنوان پیش‌فرض |

### ۴.۲. داده‌های مکانی (عمومی)

| متد | مسیر | توضیحات |
|-----|------|---------|
| GET | `/api/provinces` | لیست استان‌ها (ordered) |
| GET | `/api/provinces/{id}/cities` | لیست شهرهای یک استان |

### ۴.۳. استفاده در تسویه حساب

| متد | مسیر | توضیحات |
|-----|------|---------|
| POST | `/api/checkout` | ثبت سفارش با `user_address_id` یا ارسال آدرس جدید |

---

## ۵. Frontend

### ۵.۱. پروفایل کاربر (`/profile`) — بخش آدرس‌ها

- **لیست آدرس‌ها:** کارت‌های قابل ویرایش
- **هر کارت شامل:** label (خانه/محل کار)، استان، شهر، آدرس کامل، کد پستی، دکمه‌های ویرایش/حذف
- **عنوان «پیش‌فرض»** روی آدرس اصلی
- **دکمه «افزودن آدرس جدید»** → باز شدن فرم Modal
- **فرم افزودن/ویرایش:**
  - فیلد Label (اختیاری، با placeholder «خانه»، «محل کار»)
  - سلکت استان (با آبشاری به شهر)
  - سلکت شهر (به‌روزرسانی با AJAX بعد از انتخاب استان)
  - فیلد منطقه (اختیاری)
  - فیلد کد پستی (با اعتبارسنجی ۱۰ رقم)
  - فیلد آدرس کامل (textarea)
  - فیلد نام و شماره گیرنده (برای هدیه، اختیاری)
  - چک‌باکس «آدرس پیش‌فرض»
  - چک‌باکس «آدرس صورتحساب»

### ۵.۲. تسویه حساب (`/checkout`) — انتخاب آدرس

**کاربر لاگین کرده:**
- نمایش لیست آدرس‌های ذخیره‌شده به صورت رادیو باتن
- آدرس پیش‌فرض از قبل انتخاب شده
- دکمه «افزودن آدرس جدید» که فرم Modal را باز می‌کند

**کاربر مهمان (فاز بعدی):**
- فرم کامل آدرس با همان فیلدها

**هر دو حالت:**
- سلکت استان → سلکت شهر (cascade با AJAX)
- اعتبارسنجی سمت کلاینت

### ۵.۳. ادمین (Filament)

- **جایگزینی Textarea ساده** با فیلدهای مجزا:
  - سلکت استان (با جستجو)
  - سلکت شهر (فیلتر شده بر اساس استان)
  - فیلد کد پستی
  - Textarea آدرس
- **نمایش آدرس کاربر** در فرم سفارش به صورت Structured
- **Widget** برای مشاهده آدرس‌های یک کاربر در پروفایل کاربر در ادمین

---

## ۶. اعتبارسنجی

### ۶.۱. سمت سرور (FormRequest — `StoreAddressRequest`)

| فیلد | قانون |
|------|-------|
| `label` | nullable, max:50 |
| `province_id` | required, exists:provinces,id |
| `city_id` | required, exists:cities,id |
| `postal_code` | nullable, regex: `/^\d{10}$/` |
| `address_line` | required, min:10, max:1000 |
| `receiver_phone` | nullable, regex: `/^09\d{9}$/` |
| `is_default` | boolean |
| `is_billing` | boolean |
| محدودیت تعداد | حداکثر ۱۰ آدرس برای هر کاربر |

### ۶.۲. سمت کلاینت (Alpine.js)

- نمایش پیام‌های خطای فارسی برای هر فیلد
- غیرفعال بودن دکمه ثبت هنگام وجود خطا
- نمایش پیشنهاد شهرهای یک استان بعد از انتخاب استان

---

## ۷. امنیت و دسترسی

- **Policy:** هر کاربر فقط به آدرس‌های خود دسترسی دارد (`UserAddressPolicy`)
- **ادمین:** مدیران از طریق Filament به همه آدرس‌ها دسترسی دارند (دسترسی سطح ادمین)
- **Soft Delete:** آدرس‌های حذف شده در سفارش‌های قبلی باقی می‌مانند
- **Rate Limit:** API آدرس‌ها محدود به ۳۰ درخواست در دقیقه

---

## ۸. فازبندی پیاده‌سازی

| فاز | مدت | آیتم‌ها |
|-----|------|---------|
| **فاز ۱** | ۲-۳ روز | `provinces` + `cities` Migration, Seeder (JSON)، مدل‌ها |
| **فاز ۲** | ۱-۲ روز | `user_addresses` Migration + `UserAddress` Model + relations |
| **فاز ۳** | ۲ روز | APIهای آدرس (CRUD + Policy + FormRequest) |
| **فاز ۴** | ۲-۳ روز | APIهای استان/شهر + کامپوننت مدیریت آدرس در پروفایل (Alpine.js) |
| **فاز ۵** | ۲ روز | ادغام آدرس در تسویه حساب + cascade استان/شهر |
| **فاز ۶** | ۱ روز | به‌روزرسانی Filament Order Resource (فیلدهای مجزا) |

---

## ۹. وابستگی‌ها

| وابستگی | توضیح |
|---------|-------|
| فعال بودن ماژول Order | فاز ۵ نیاز به ثبت سفارش واقعی دارد |
| لاگین کاربر | فاز ۴ و ۵ به کاربر احراز هویت شده نیاز دارند |
| تکمیل فاز ۱ | فاز ۲ به جداول provinces و cities وابسته است |
