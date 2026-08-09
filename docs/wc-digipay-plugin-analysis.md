# تحلیل پلاگین درگاه دیجی‌پی برای ووکامرس (wc-digipay-plugin)

## خلاصه کلی

این سند نحوه‌ی کار پلاگین رسمی دیجی‌پی برای ووکامرس را مستند می‌کند. پلاگین در پوشه‌ی
`wp-content/plugins/wc-digipay-plugin` قرار دارد و نسخه‌ی فعلی آن **1.6.10** است. این پلاگین مرجعِ رسمی دیجی‌پی است و نحوه‌ی صحیح کال کردن API دیجی‌پی را نشان می‌دهد.

> **نکته بسیار مهم:** نحوه‌ی وریفای در این پلاگین (نسخه‌ی 1.6.8 به بعد) **با پیاده‌سازی بک‌اند لاراول متفاوت است** و به‌نظر می‌رسد پیاده‌سازی لاراول قدیمی است. جزئیات در بخش «مقایسه با بک‌اند لاراول» آمده است.

---

## ساختار فایل‌ها

```
wc-digipay-plugin/
├── index.php                  ← فایل اصلی پلاگین (بووت‌استرپ)
├── wc-dp-gateway.php          ← ثبت درگاه در ووکامرس
├── WCDigiPay.php              ← کلاس درگاه پرداخت (extends WC_Payment_Gateway)
├── DPGateway.php              ← کلاس کلاینت API دیجی‌پی (HTTP + OAuth)
├── Utilities.php              ← توابع کمکی (نرمال‌سازی شماره موبایل و اعداد)
├── includes/update.php        ← مکانیزم آپدیت خودکار پلاگین
├── assets/js/digipay-checkout.js  ← اسکریپت checkout
└── logo.png
```

| فایل | نقش |
|------|------|
| `index.php` | بووت‌استرپ؛ تعریف ثابت‌ها (نسخه، مسیر، آدرس سرور آپدیت)، لود کردن `update.php` و `wc-dp-gateway.php` |
| `wc-dp-gateway.php` | هوک `plugins_loaded`؛ افزودن `WCDigiPay` به لیست درگاه‌ها + ثبت ارزهای IRR/IRT |
| `WCDigiPay.php` | کلاس درگاه؛ تنظیمات، کارمزد ۴٪، purchase (ایجاد بلیط)، callback و وریفای، ریفاند، متاباکس سفارش |
| `DPGateway.php` | کلاینت خام HTTP با cURL؛ OAuth، `createTicket`، `verifyTicket`، `refund` |

---

## بووت‌استرپ

1. **`index.php`**:
   - نسخه را از هدر فایل می‌خواند و در `WC_DP_VERSION` می‌گذارد
   - `WC_DP_UPDATE_API` را بر اساس محیط (`wp_get_environment_type()`) تعیین می‌کند:
     - `local` → `http://localhost:3240/wp-plugin/check-update`
     - `production`/پیش‌فرض → `https://www.mydigipay.com/wp-plugin/check-update`
   - `includes/update.php` و `wc-dp-gateway.php` را لود می‌کند

2. **`wc-dp-gateway.php`** — هوک `plugins_loaded` با اولویت ۰:
   - درگاه `WCDigiPay` را به `woocommerce_payment_gateways` اضافه می‌کند
   - ارزهای `IRR` (ریال) و `IRT` (تومان) و سمبل‌هایشان را به ووکامرس اضافه می‌کند
   - `WCDigiPay.php` را لود می‌کند

3. **`WCDigiPay.php`** — کلاس درگاه، `WC_Payment_Gateway` را extend می‌کند و در constructor:
   - فیلدهای تنظیمات و مقادیر ذخیره‌شده را می‌خواند
   - هوک‌ها را ثبت می‌کند:
     - `woocommerce_receipt_WCDigiPay` → صفحه‌ی پرداخت
     - `woocommerce_api_wcdigipay` → callback
     - `woocommerce_cart_calculate_fees` → کارمزد ۴٪
     - `wp_enqueue_scripts` → لود اسکریپت checkout

---

## فلوی پرداخت

```
ووکامرس checkout
   │  انتخاب درگاه WCDigiPay → کارمزد ۴٪ به سبد اضافه می‌شود
   ▼
process_payment() → redirect به صفحه receipt (get_checkout_payment_url)
   ▼
digiPayPaymentRequest()  ← صفحه‌ی پرداخت، فرم POST با دکمه «پرداخت»
   │   • مبلغ به IRR تبدیل می‌شود (convertToIRR)
   │   • شماره موبایل نرمال‌سازی می‌شود (Utilities::mobileNumberStandardizer)
   │   • سبد به basketDetailsDto تبدیل می‌شود
   ▼
DPGateway::createTicket()  ← POST /digipay/api/tickets/business?type=11
   │   بادی JSON: { amount, cellNumber, providerId, callbackUrl, basketDetailsDto }
   ▼
redirect کاربر به redirectUrl دیجی‌پی
   ▼
پرداخت در دیجی‌پی → POST به callbackUrl (با wc_order در query string)
   ▼
digiPayPaymentCallback()
   │   • خواندن result / amount / trackingCode / type از بادی
   │   • اگر result === 'SUCCESS':
   │        • تطبیق مبلغ (amount == مبلغ سفارش)
   │        • DPGateway::verifyTicket($trackingCode, $order_id, $type)
   │        • ذخیره trackingCode/type در post_meta
   │        • $order->payment_complete($trackingCode) و خالی کردن سبد
   ▼
redirect به صفحه‌ی تشکر (get_return_url) با wc_status=success
```

---

## سرویس‌های API (DPGateway)

کلاس `DPGateway` با cURL خام همه‌ی درخواست‌ها را می‌فرستد. بیس URL:

```php
$this->liveApi ? 'https://api. .com' : 'https://uat.mydigipay.info'
```

> ⚠️ آدرس لایو در ریپو گیت **مبهم‌سازی (redact) شده** (`https://api. .com`) و باید عملاً `https://api.mydigipay.com` باشد. آدرس تست: `https://uat.mydigipay.info`.

هدرهای مشترک هر درخواست:
```
Content-Type: application/json (یا form-urlencoded برای OAuth)
Agent: WEB
Digipay-Version: 2022-02-02
Plugin-Version: {WC_DP_VERSION}
```

### ۱. احراز هویت — `authenticate()`

```
POST /digipay/api/oauth/token
Authorization: Basic base64(client_id:client_secret)
```

- بادی form-urlencoded: اگر `refresh_token` موجود باشد با `grant_type=refresh_token`، در غیر این صورت با `username`، `password` و `grant_type=password`
- پاسخ `access_token` و `refresh_token` را در تنظیمات پلاگین ذخیره می‌کند (آپشن `woocommerce_WCDigiPay_settings`)
- اگر در هر درخواستی `401` برگردد، خودکار توکن را پاک و دوباره `authenticate()` و همان درخواست را یک‌بار تکرار می‌کند

### ۲. ایجاد بلیط — `createTicket()`

```
POST /digipay/api/tickets/business?type=11
Authorization: Bearer {access_token}
```

بادی JSON:
```json
{
  "amount": 50000000,
  "cellNumber": "0912xxxxxxx",
  "providerId": 142,
  "callbackUrl": "https://.../wc-api/WCDigiPay/?wc_order=142",
  "basketDetailsDto": {
    "basketId": 142,
    "items": [
      { "sellerId": 1, "supplierId": 1, "productCode": "SKU1", "brand": "",
        "productType": 1, "count": 2, "categoryId": 1 }
    ]
  }
}
```

- `type` ثابت `11` است (WALLET)
- `providerId` = شماره سفارش (`$order->get_order_number()`)
- پاسخ: `redirectUrl` و `ticket`

### ۳. وریفای — `verifyTicket()` ⭐

```
POST /digipay/api/purchases/verify?type={type}
Authorization: Bearer {access_token}
```

بادی JSON (مهم — این نسخه‌ی فعلی و صحیح است):
```json
{
  "trackingCode": "1656...",
  "providerId": 142
}
```

- آدرس **بدون** trackingCode در مسیر است و هر دو مقدار در **بادی** فرستاده می‌شوند
- `type` در query string (مقدار دریافتی از callback، نه مقدار ثابت)
- اگر `result.status == 0` → موفق (برمی‌گرداند `true`)
- اگر `result.status == 9011` → پرداخت هنوز در حالت pending است؛ ۵ ثانیه صبر و تا **۳ بار** دوباره تلاش می‌کند
- در غیر این صورت Exception با پیام `result.message`

**تاریخچه‌ی تغییر (V1.6.8 — commit e318e28، دی ۱۴۰۴):** قبل از این نسخه وریفای به این شکل بود:
```
POST /digipay/api/purchases/verify/{trackingCode}?type={type}   ← بادی خالی
```
که از نسخه‌ی 1.6.8 به بعد به حالت بادی‌محور (با `providerId` + `trackingCode`) تغییر کرده است.

### ۴. ریفاند — `refund()`

```
POST /digipay/api/refunds?type={type}
```

بادی JSON:
```json
{
  "amount": 100000,
  "providerId": "142___123",         // id سفارش + عدد رندوم (باید یکتا باشد)
  "saleTrackingCode": "1656...",
  "description": "دلیل"
}
```

- از `process_refund` ووکامرس صدا زده می‌شود
- `providerId` ریفاند باید با providerId خرید متفاوت باشد (چون یکتا لازم است)

---

## کارمزد خرید اقساطی (۴٪)

در `digipayMaybeAddCheckoutFee`:

- فقط وقتی درگاه `WCDigiPay` انتخاب شده باشد
- مبلغ پایه = محتوای سبد + مالیات + هزینه‌ی ارسال (+ مالیاتش)
- کارمزد = `round(baseTotal * 4 / 100)` — ثابت `DIGIPAY_FEE_PERCENT = 4`
- با عنوان «کارمزد خرید اقساطی (۴٪)» به سبد اضافه می‌شود

اسکریپت `digipay-checkout.js` تضمین می‌کند که با تغییر روش پرداخت، `update_checkout` اجرا شود تا کارمزد درست محاسبه/حذف شود (مخصوص قالب‌های سفارشی).

---

## callback و وریفای — جزئیات

در `digiPayPaymentCallback` (روت `wc-api/WCDigiPay`):

1. `order_id` از `$_GET['wc_order']` یا session خوانده می‌شود
2. از بادی POST: `result`، `amount`، `trackingCode`، `type` (پیش‌فرض `0`)
3. اگر سفارش قبلاً `completed` نباشد و `result === 'SUCCESS'`:
   - تطبیق `amount` دریافتی با مبلغ سفارش (به IRR) — اگر متفاوت بود رد می‌شود
   - `verifyTicket($trackingCode, $order_id, $type)`
   - بعد از موفقیت:
     - `_transaction_id` و `_dp_tracking_code` ← trackingCode
     - `_dp_type` ← type
     - `$order->payment_complete($trackingCode)` و `$woocommerce->cart->empty_cart()`
     - ریدایرکت به صفحه‌ی تشکر
4. هر نوع خطا → پیام از `failed_massage` (شورت‌کدهای `{transaction_id}` و `{fault}`) + ریدایرکت به checkout

متاباکس «داده‌های درگاه پرداخت دیجی‌پی» در صفحه‌ی ویرایش سفارش، کد رهگیری و `type` را نشان می‌دهد و اجازه‌ی ویرایش دستی می‌دهد (مقادیر: 0=IPG، 11=WALLET، 5=CREDIT، 13=BNPL، 24=CREDIT-CARD).

---

## آپدیت خودکار (includes/update.php)

- با `pre_set_site_transient_update_plugins` نسخه‌ی جدید را از `WC_DP_UPDATE_API` (با transient ۶ ساعته) می‌گیرد
- با `plugins_api` اطلاعات پلاگین را برای صفحه‌ی جزئیات کامل می‌کند
- با `admin_notices` اگر نسخه‌ی جدیدی باشد، نوتیف در پنل نمایش می‌دهد

---

## مقایسه با بک‌اند لاراول ⭐

این مهم‌ترین بخش است. نحوه‌ی وریفای در دو پیاده‌سازی **متفاوت** است:

| | پلاگین وردپرس (رسمی، v1.6.8+) | بک‌اند لاراول (Shetabit) |
|--|------------------------------|--------------------------|
| آدرس | `POST /digipay/api/purchases/verify?type={type}` | `POST /digipay/api/purchases/verify/{trackingCode}?type={type}` |
| بادی | **JSON شامل `trackingCode` و `providerId`** | **بدون بادی** |
| `providerId` | ارسال می‌شود | ارسال نمی‌شود |
| `trackingCode` | در بادی | در مسیر URL |

پیاده‌سازی لاراول دقیقاً همان فرمت **قدیمی** پلاگین (قبل از V1.6.8) را دارد. چون پلاگین رسمی در **دی ۱۴۰۴** به فرمت بادی‌محور مهاجرت کرده، به احتمال زیاد API فعلی دیجی‌پی همین فرمت را انتظار دارد و درایور Shetabit در بک‌اند لاراول **باید به‌روزرسانی شود** تا `trackingCode` و `providerId` را در بادی JSON بفرستد.

مسیرهای مربوطه در لاراول:
- `app/Payment/Drivers/CustomDigipay.php` (متد `verify()` را override نکرده — از والد ارث می‌برد)
- `vendor/shetabit/multipay/src/Drivers/Digipay/Digipay.php` (متد `verify()` قدیمی)
- `app/Http/Controllers/Api/PaymentController.php` (فراخوانی `verify()`)

---

## نکات و مشکلات کد

- **خط ۴۷ `DPGateway.php`**: آدرس لایو مبهم‌سازی شده (`https://api. .com`) — باید `https://api.mydigipay.com` باشد.
- **خط ۷۶ `DPGateway.php`**: `if (curl_errno($ch) === false)` هیچ‌وقت true نمی‌شود (curl_errno عدد int برمی‌گرداند)؛ عملاً خطاهای cURL تشخیص داده نمی‌شوند.
- **مبلغ**: پلاگین مبلغ را با `convertToIRR` بسته به ارز (IRR/IRT/IRHR/IRHT) به ریال تبدیل می‌کند؛ لاراول در Shetabit از `currency == 'T' ? *10 : 1` استفاده می‌کند.
- **`type` پیش‌فرض در callback** پلاگین `0` (IPG) است اما در createTicket `11` (WALLET) فرستاده می‌شود؛ مقدار واقعی نوع پرداخت‌شده از callback خوانده می‌شود.

---

## فایل‌های کلیدی برای مراجعه

| فایل | وظیفه |
|------|--------|
| `index.php` | بووت‌استرپ و تعریف ثابت‌ها |
| `wc-dp-gateway.php` | ثبت درگاه و ارزها در ووکامرس |
| `WCDigiPay.php` | process_payment، receipt، callback/verify، ریفاند، کارمزد ۴٪، متاباکس |
| `DPGateway.php` | کلاینت cURL، OAuth، createTicket، verifyTicket، refund |
| `includes/update.php` | آپدیت خودکار |
| `assets/js/digipay-checkout.js` | رفرش checkout هنگام تغییر روش پرداخت |
