# فلوی پرداخت با درگاه کمان‌لند (Kamanlend)

## خلاصه کلی

درگاه پرداخت اعتباری کمان‌لند به کاربران اجازه میده با موجودی کیف پول کمان خرید کنن. فلوی پرداخت شامل ۳ مرحله اصلی هست: **ثبت درخواست** → **پرداخت در درگاه** → **تایید و وریفای**.

---

## معماری کلی

```
┌─────────────┐     ┌──────────────────┐     ┌─────────────────────┐
│  فرانت‌اند   │────▶│  بک‌اند لاراول    │────▶│  درگاه کمان‌لند      │
│  (Next.js)  │◀────│  (PaymentCtrl)   │◀────│  (sandbox/prod)     │
└─────────────┘     └──────────────────┘     └─────────────────────┘
                            │
                            ▼
                    ┌──────────────────┐
                    │   دیتابیس        │
                    │  orders/payments │
                    └──────────────────┘
```

---

## فلوی قدم به قدم

### مرحله ۱: ثبت سفارش (فرانت‌اند → بک‌اند)

**فرانت‌اند**: کاربر آدرس و روش ارسال رو انتخاب میکنه و سفارش رو ثبت میکنه.

```
POST /api/orders
{
  "user_address_id": 1,
  "shipping_method_id": 2,
  "gateway": "kamanlend"
}
```

**بک‌اند** (`OrderSubmitController@store`):
1. آدرس کاربر رو از `user_address_id` میخونه
2. کد ملی رو از آدرس یا پروفایل کاربر میگیره (`address.receiver_national_code` یا `user.national_code`)
3. سفارش رو با وضعیت `pending` ذخیره میکنه
4. شماره سفارش ۵ رقمی با صفر پر شده (مثلاً `21000`)

**خروجی**: شماره سفارش و اطلاعات سفارش

---

### مرحله ۲: شروع پرداخت (فرانت‌اند → بک‌اند → کمان‌لند)

**فرانت‌اند**: دکمه "پرداخت" رو میزنه و gateway رو `kamanlend` انتخاب میکنه.

```
POST /api/payment/init
{
  "order_id": 142,
  "gateway": "kamanlend"
}
```

**بک‌اند** (`PaymentController@init`):
1. سفارش و آدرس کاربر رو لود میکنه (`Order::with(['user', 'address'])`)
2. کد ملی رو از ریلیشن میگیره: `$order->address->receiver_national_code`
3. کانفیگ‌های کمان‌لند رو میخونه:
   - `KAMANLEND_TERMINAL_CODE`
   - `KAMANLEND_TERMINAL_SECRET`
   - `KAMANLEND_API_REGISTER_URL`
   - `KAMANLEND_GATEWAY_URL`
4. Invoice رو میسازه با اطلاعات سفارش

**کمان‌لند Driver** (`Kamanlend::purchase()`):
1. درخواست POST به `{gateway_url}/api/Gateway/RegisterPayment` میفرسته:
   ```json
   {
     "terminalCode": "sawissshop",
     "terminalSecret": "sawissshop",
     "customerNationalCode": "4900508349",
     "shoppingCardCode": "1784726227",
     "stateChangeCallbackUrl": "https://backend.sawiss.com/api/payment/callback/142/kamanlend",
     "redirectionUrl": "https://new.sawiss.com/confirm?order_id=142",
     "saleItems": [
       {
         "code": "0",
         "title": "پرداخت سفارش 21000",
         "quantity": 1,
         "totalAmountRial": 80080000
       }
     ]
   }
   ```
2. پاسخ شامل `token` و `gatewayUrl` هست
3. رکورد پرداخت با وضعیت `pending` ذخیره میشه

**خروجی**: آدرس درگاه پرداخت (`payment_url`)

**نکته مهم**: دو URL متفاوت فرستاده میشه:
- `stateChangeCallbackUrl` → سرور به سرور (کمان‌لند وضعیت رو به بک‌اند اعلام میکنه)
- `redirectionUrl` → ریدایرکت مرورگر کاربر بعد از پرداخت (صفحه تایید فرانت)

---

### مرحله ۳: پرداخت در درگاه (کاربر → کمان‌لند)

1. کاربر به `gatewayUrl` ریدایرکت میشه
2. در صفحه کمان‌لند:
   - کد ملی کاربر نمایش داده میشه
   - موجودی کیف پول نمایش داده میشه
   - کاربر دکمه "دریافت کد پرداخت" رو میزنه
   - کد OTP به شماره موبایل ارسال میشه
   - کاربر کد رو وارد میکنه و پرداخت رو تایید میکنه
3. کمان‌لند وضعیت رو به `stateChangeCallbackUrl` اعلام میکنه (سرور به سرور)
4. کاربر به `redirectionUrl` ریدایرکت میشه (فرانت)

---

### مرحله ۴: تایید پرداخت (کمان‌لند → بک‌اند → فرانت)

**بک‌اند** (`PaymentController@callback`):
1. درخواست GET از کمان‌لند دریافت میشه:
   ```
   GET /api/payment/callback/142/kamanlend
   ```
2. رکورد پرداخت رو با وضعیت `processing` آپدیت میکنه (با lock)
3. **وریفای** انجام میشه (`Kamanlend::verify()`):
   - درخواست POST به `{gateway_url}/api/Gateway/GetPaymentState`
   - پاسخ شامل `saleRequestState: "PaymentCompleted"` هست
4. در صورت موفقیت:
   - `payment.status` → `paid`
   - `order.payment_status` → `paid`
   - `order.status` → `confirmed`
   - کد رهگیری ذخیره میشه
5. کاربر به صفحه تایید فرانت ریدایرکت میشه:
   ```
   https://new.sawiss.com/confirm?order_id=142
   ```

**فرانت‌اند** (`ConfirmPage`):
1. `order_id` رو از URL میخونه
2. API `GET /api/orders/{id}` رو صدا میزنه (نیاز به auth token)
3. شماره سفارش، مبلغ و روش ارسال رو نمایش میده

---

## کانفیگ‌های محیطی (.env)

```env
# Production (فعال)
KAMANLEND_TERMINAL_CODE="sawissshop"
KAMANLEND_TERMINAL_SECRET="sawissshop"
KAMANLEND_API_REGISTER_URL=https://gateway.sandbox.kamanlend.ir/api/Gateway/RegisterPayment
KAMANLEND_API_STATE_URL=https://gateway.sandbox.kamanlend.ir/api/Gateway/GetPaymentState
KAMANLEND_GATEWAY_URL=https://gateway.sandbox.kamanlend.ir

# Sandbox (کامنت شده)
#KAMANLEND_TERMINAL_CODE=sawiss
#KAMANLEND_TERMINAL_SECRET="T7JGGgM1906B1hacQY1nWMYLkRttcC5V"
#KAMANLEND_API_REGISTER_URL=https://gateway.kamanlend.ir/api/Gateway/RegisterPayment
#KAMANLEND_API_STATE_URL=https://gateway.kamanlend.ir/api/Gateway/GetPaymentState
#KAMANLEND_GATEWAY_URL=https://gateway.kamanlend.ir
```

---

## فایل‌های کلیدی

| فایل | وظیفه |
|------|--------|
| `app/Payment/Drivers/Kamanlend/Kamanlend.php` | درایور اصلی - purchase, pay, verify |
| `app/Http/Controllers/Api/PaymentController.php` | کنترلر پرداخت - init, callback, status |
| `app/Http/Controllers/Api/OrderSubmitController.php` | ثبت سفارش - store |
| `config/payment.php` | کانفیگ درگاه‌های پرداخت |
| `src/app/pages/ConfirmPage.tsx` | صفحه تایید پرداخت (فرانت) |

---

## لاگ‌ها

لاگ‌های پرداخت در `storage/logs/payment.log` ذخیره میشن:

```
Payment init started          → شروع درخواست پرداخت
Kamanlend purchase request    → درخواست به API کمان‌لند
Kamanlend purchase response   → پاسخ API
Payment init success          → موفقیت ثبت درخواست
Payment callback received     → دریافت callback از کمان‌لند
Kamanlend verify request      → درخواست وریفای
Kamanlend verify response     → پاسخ وریفای
```

---

## باگ‌های رفع شده

### ۱. `array_change_key_case` خرابکاری میکرد
**مشکل**: تابع `array_change_key_case($body, CASE_LOWER)` فقط کلیدهای سطح اول رو lowercase میکرد. کلیدهای توی `result` مثل `saleRequestState` دست‌نخورده میموند ولی کد دنبال `salerequeststate` میگشت.

**راه‌حل**: حذف `array_change_key_case` و دسترسی مستقیم به کلیدها با هر دو حالت.

### ۲. کد ملی در وریفای ارسال نمیشد
**مشکل**: در callback، جزئیات Invoice در دسترس نبود و کد ملی خالی بود.

**راه‌حل**: استفاده از ریلیشن `Payment → Order → Address/User` برای دریافت کد ملی.

### ۳. `redirectionUrl` اشتباه بود
**مشکل**: `redirectionUrl` به آدرس بک‌اند اشاره میکرد باید به فرانت باشه.

**راه‌حل**: تغییر به `https://new.sawiss.com/confirm?order_id=XXX`.

---

## تست

1. با کاربر لاگین کن
2. محصولی رو به سبد خرید اضافه کن
3. در checkout، کمان‌لند رو انتخاب کن
4. کد ملی رو وارد کن
5. در درگاه کمان‌لند، پرداخت رو تایید کن
6. باید به صفحه تایید فرانت ریدایرکت بشی
7. شماره سفارش و روش ارسال باید نمایش داده بشه
8. در admin، وضعیت سفارش `confirmed` و پرداخت `paid` باشه
