# مستندات فنی پروژه - ماژول محصولات

## معماری کلی

این پروژه از معماری ماژولار با استفاده از بسته `nwidart/laravel-modules` استفاده می‌کند. ماژول Product در مسیر `Modules/Product` قرار دارد.

### ساختار دایرکتوری‌ها

```
Modules/Product/
├── app/
│   ├── Models/
│   │   ├── Product.php
│   │   └── ProductImage.php
│   └── Providers/
│       ├── ProductServiceProvider.php
│       ├── EventServiceProvider.php
│       └── RouteServiceProvider.php
├── database/
│   └── migrations/
│       ├── 2026_06_14_102843_create_products_table.php
│       └── 2026_06_14_102854_create_product_images_table.php
├── config/
│   └── config.php
├── routes/
│   ├── web.php
│   └── api.php
├── resources/
│   └── views/
├── module.json
└── composer.json
```

### Filament Resource

ریسورس Filament برای مدیریت محصولات در مسیر پیش‌فرض قرار دارد:

```
app/Filament/Resources/Products/
├── ProductResource.php
├── Pages/
│   ├── CreateProduct.php
│   ├── EditProduct.php
│   └── ListProducts.php
├── Schemas/
│   └── ProductForm.php
└── Tables/
    └── ProductsTable.php
```

---

## دیتابیس

### جدول `products`

| فیلد | نوع | توضیحات |
|------|-----|---------|
| id | bigint, auto-increment | شناسه یکتا |
| name | varchar(255) | نام محصول |
| slug | varchar(255), unique | Slug یکتا |
| description | longtext, nullable | توضیحات کامل (rich text) |
| weight | decimal(10,2), nullable | وزن محصول (kg) |
| price | decimal(12,2) | قیمت محصول |
| stock_quantity | integer, default: 0 | موجودی انبار |
| sort_order | integer, default: 0 | ترتیب نمایش |
| created_at | timestamp | تاریخ ایجاد |
| updated_at | timestamp | تاریخ بروزرسانی |

### جدول `product_images`

| فیلد | نوع | توضیحات |
|------|-----|---------|
| id | bigint, auto-increment | شناسه یکتا |
| product_id | bigint, FK → products.id | محصول مرتبط |
| image_path | varchar(255) | مسیر فایل تصویر |
| is_primary | boolean, default: false | تصویر اصلی |
| sort_order | integer, default: 0 | ترتیب نمایش |
| created_at | timestamp | تاریخ ایجاد |
| updated_at | timestamp | تاریخ بروزرسانی |

---

## مدل‌های Eloquent

### `Modules\Product\Models\Product`

- **fillable**: name, slug, description, weight, price, stock_quantity, sort_order
- **casts**: weight (decimal:2), price (decimal:2), stock_quantity (integer), sort_order (integer)
- **relationships**:
  - `images()`: HasMany → ProductImage
  - `primaryImage()`: HasOne → ProductImage (where is_primary = true)

### `Modules\Product\Models\ProductImage`

- **fillable**: product_id, image_path, is_primary, sort_order
- **casts**: is_primary (boolean), sort_order (integer)
- **relationships**:
  - `product()`: BelongsTo → Product

---

## Filament Resource

### ProductForm

فرم ایجاد/ویرایش محصول شامل فیلدهای زیر است:

- **name** (TextInput): با قابلیت auto-slug بر رویداد blur
- **slug** (TextInput): یکتا، با امکان ignore رکورد جاری در ویرایش
- **description** (RichEditor): ادیتور متن کامل با پشتیبانی از HTML
- **price** (TextInput): عددی با پیشوند $
- **weight** (TextInput): عددی با پسوند kg
- **stock_quantity** (TextInput): عددی، پیش‌فرض ۰
- **sort_order** (TextInput): عددی، پیش‌فرض ۰
- **images** (Repeater): ریپیتر برای مدیریت تصاویر محصول شامل:
  - FileUpload (آپلود تصویر)
  - Toggle is_primary (تعیین تصویر اصلی)
  - sort_order

### ProductsTable

- ستون‌ها: name, price, stock_quantity, sort_order, created_at
- sort پیش‌فرض بر اساس sort_order
- قابلیت search در name
- bulk delete

---

## نکات فنی

### Auto-slaug

هنگام وارد کردن نام محصول، Slug به صورت خودکار با تابع `Str::slug()` ساخته می‌شود. این قابلیت با استفاده از `live(onBlur: true)` روی فیلد name پیاده‌سازی شده است.

### مدیریت تصاویر

تصاویر محصول از طریق یک Repeater درون فرم اصلی مدیریت می‌شوند. فیلد `is_primary` مشخص می‌کند کدام تصویر به عنوان تصویر اصلی محصول نمایش داده شود. در صورت فعال کردن Toggle یک تصویر، سایر تصاویر به صورت خودکار غیرفعال می‌شوند.

### Autoloading

ماژول‌ها با استفاده از `wikimedia/composer-merge-plugin` به صورت خودکار در composer autoload ادغام می‌شوند. فایل `composer.json` هر ماژول حاوی PSR-4 mapping مربوط به خود است.
