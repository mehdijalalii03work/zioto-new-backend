# سرویس وریفای درگاه دیجی‌پی (DigiPay)

## خلاصه کلی

این سند نحوه‌ی فراخوانی سرویس **وریفای (Verify)** درگاه دیجی‌پی در بک‌اند را مستند می‌کند. وریفای آخرین قدم از فلوی پرداخت است که بعد از برگشت کاربر از درگاه و دریافت callback انجام می‌شود تا تراکنش قطعی (settle) شود.

---

## مسیر فراخوانی

```
Digipay callback (POST) → PaymentController@callback → ShetabitPayment->verify()
                                                          │
                                                          ▼
                                   CustomDigipay::verify() (ارث‌بری از پکیج Shetabit)
                                                          │
                                                          ▼
                          POST {apiPaymentUrl}/digipay/api/purchases/verify/{trackingCode}?type={type}
```

### فایل‌های درگیر

| فایل | نقش |
|------|------|
| `app/Http/Controllers/Api/PaymentController.php` | کنترلر callback - تراکنش را لاک می‌کند و `verify()` را صدا می‌زند |
| `app/Payment/Drivers/CustomDigipay.php` | درایور سفارشی - فقط متد `oauth()` را override کرده، `verify()` از کلاس والد ارث‌بری می‌شود |
| `vendor/shetabit/multipay/src/Drivers/Digipay/Digipay.php` | کلاس والد - متد `verify()` درخواست HTTP را می‌سازد |
| `config/payment.php` | کانفیگ درایور `digipay` |

نکته: درایور `CustomDigipay` متد `verify()` را override نکرده است؛ بنابراین رفتار وریفای دقیقاً همان متد کلاس والد در پکیج Shetabit است.

---

## درخواست وریفای

فراخوانی واقعی که به دیجی‌پی ارسال می‌شود (در `Digipay.php` متد `verify()`):

```
POST {apiPaymentUrl}/digipay/api/purchases/verify/{trackingCode}?type={type}
```

### هدرها

```
Authorization: Bearer {oauthToken}
Accept: application/json
```

### بادی

**بدون بادی (body) — بادی ارسال نمی‌شود.**

### پارامترها

| پارامتر | مکان | منبع مقدار |
|---------|------|------------|
| `trackingCode` | در **مسیر URL** (path) | `Request::input('trackingCode')` — از بادی خود callback |
| `type` | در **query string** | `Request::input('type')` — از بادی خود callback |
| `providerId` | **ارسال نمی‌شود** | — |

مقدار `trackingCode` و `type` از بادی (form) همان درخواست callback خوانده می‌شوند که دیجی‌پی به بک‌اند POST می‌کند.

---

## نکات مهم

### ۱. `providerId` در وریفای ارسال نمی‌شود

برخلاف purchase که `providerId` را در بادی JSON می‌فرستد، سرویس وریفای دیجی‌پی **نیازی به `providerId` ندارد**. طبق مستندات رسمی UPG دیجی‌پی (api.mydigipay.com):

- وریفای فقط `trackingCode` را در مسیر و `type` را به‌عنوان query param دارد
- `providerId` فقط در درخواست‌های `purchase`، `reverse` و `refund` استفاده می‌شود

این رفتار با سایر پیاده‌سازی‌های مرجع این API (مثلاً پیاده‌سازی Go پکیج payvand) یکسان است.

### ۲. منبع `trackingCode`

وریفای روی **کد رهگیری دیجی‌پی** انجام می‌شود که در callback توسط خود دیجی‌پی ارسال می‌شود، نه روی `transaction_id` که در مرحله purchase ذخیره شده است. به همین دلیل اگر `trackingCode` در callback نیامده باشد، وریفای با خطا مواجه می‌شود.

### ۳. احراز هویت

توکن OAuth در متد `oauth()` کلاس `CustomDigipay` ساخته می‌شود:

```
POST {apiPaymentUrl}/digipay/api/oauth/token
```

- هدر `Authorization: Basic base64(client_id:client_secret)`
- بادی multipart شامل `username`، `password` و `grant_type=password`
- توکن دریافتی در هدر `Bearer` همه درخواست‌ها استفاده می‌شود

### ۴. پاسخ

- کد وضعیت `200` → وریفای موفق، رکورد `Receipt` با `trackingCode` و جزئیات پاسخ ساخته می‌شود
- کد وضعیت غیر از `200` → استثنای `InvalidPaymentException` با پیام خطا از `result.message`

### ۵. خطاهای رایج

- `trackingCode` در callback خالی/گم شده → وریفای به آدرس ناقص ارسال می‌شود
- مقدار `type` اشتباه باشد (نوع محصولی که واقعاً پرداخت شده با مقدار پیش‌فرض متفاوت باشد) → دیجی‌پی وریفای را رد می‌کند

---

## کانفیگ محیطی (.env)

```env
DIGIPAY_API_URL=https://api.mydigipay.com
DIGIPAY_USERNAME=
DIGIPAY_PASSWORD=
DIGIPAY_CLIENT_ID=
DIGIPAY_CLIENT_SECRET=
```

---

## روت‌های callback

```php
Route::match(['get', 'post'], '/callback/{orderId}/{gateway}', [PaymentController::class, 'callback']);
```

دیجی‌پی بعد از پرداخت، کاربر را با POST به این آدرس (با `{gateway}=digipay`) ریدایرکت می‌کند و بادی شامل فیلدهایی مثل `trackingCode`، `type`، `providerId`، `result` ارسال می‌شود.
