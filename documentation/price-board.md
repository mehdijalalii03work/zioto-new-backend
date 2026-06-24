# مستندات فنی - تابلو قیمت لحظه‌ای

## معماری کلی

تابلو قیمت لحظه‌ای یک سیستم Real-Time است که هر دقیقه قیمت‌های فلزات گران‌بها را از سرویس خارجی Tokeniko دریافت کرده، در Redis ذخیره می‌کند و از طریق WebSocket به فرانت‌اند ارسال می‌کند.

### نمودار جریان

```
┌─────────────────┐     هر دقیقه      ┌──────────────────┐
│  Laravel Scheduler│ ──────────────▶  │  SyncPriceBoard   │
│  (routes/console) │                  │  Command          │
└─────────────────┘                   └────────┬─────────┘
                                               │
                                    ┌──────────▼──────────┐
                                    │  PriceBoardService   │
                                    │  - fetch API         │
                                    │  - store in Redis    │
                                    └──────────┬──────────┘
                                               │
                                    ┌──────────▼──────────┐
                                    │  PriceBoardUpdated   │
                                    │  Event (Broadcast)   │
                                    └──────────┬──────────┘
                                               │
                                    ┌──────────▼──────────┐
                                    │   Laravel Reverb     │
                                    │   (WebSocket Server) │
                                    └──────────┬──────────┘
                                               │
                                    ┌──────────▼──────────┐
                                    │   فرانت‌اند           │
                                    │   usePriceBoard hook │
                                    └─────────────────────┘
```

---

## فایل‌های مرتبط

### بک‌اند

| فایل | مسیر | وظیفه |
|------|------|-------|
| PriceBoardService | `app/Services/PriceBoardService.php` | دریافت قیمت از API و ذخیره در Redis |
| SyncPriceBoard | `app/Console/Commands/Tokeniko/SyncPriceBoard.php` | کامناد Artisan برای اجرا توسط Scheduler |
| PriceBoardUpdated | `app/Events/PriceBoardUpdated.php` | Event Broadcasting برای ارسال قیمت‌ها |
| PriceBoardController | `app/Http/Controllers/Api/PriceBoardController.php` | API Endpoint برای دریافت قیمت‌های فعلی |
| channels.php | `routes/channels.php` | تعریف کانال عمومی `price-board` |
| console.php | `routes/console.php` | Scheduler هر دقیقه |
| api.php | `routes/api.php` | Route `GET /api/price-board` |

### فرانت‌اند

| فایل | مسیر | وظیفه |
|------|------|-------|
| usePriceBoard | `src/lib/usePriceBoard.ts` | هوک WebSocket برای دریافت قیمت‌ها |
| PricesPage | `src/app/pages/PricesPage.tsx` | صفحه تابلو قیمت |
| .env.local | `.env.local` | متغیرهای Reverb |

---

## جزئیات پیاده‌سازی

### ۱. دریافت قیمت از API خارجی

**فایل**: `app/Services/PriceBoardService.php`

سرویس از آدرس `https://tokeniko.com/api/prices-with-change` درخواست GET ارسال می‌کند:

```php
// کلید Redis برای ذخیره قیمت‌ها
private const CACHE_KEY = 'priceboard:prices';
private const CACHE_TTL = 120; // ۲ دقیقه
```

- پاسخ API مستقیماً در Redis ذخیره می‌شود (بدون تبدیل)
- TTL کش: ۱۲۰ ثانیه
- در صورت خطا، قیمت‌های قبلی از Redis برگردانده می‌شوند

### ۲. Scheduler (اجرا هر دقیقه)

**فایل**: `routes/console.php`

```php
Schedule::command('priceboard:sync')
    ->everyMinute()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/priceboard-sync.log'));
```

- بدون همپوشانی (`withoutOverlapping`)
- لاگ در `storage/logs/priceboard-sync.log`

### ۳. Broadcasting Event

**فایل**: `app/Events/PriceBoardUpdated.php`

```php
class PriceBoardUpdated implements ShouldBroadcast
{
    public function broadcastOn(): array
    {
        return [new Channel('price-board')];
    }

    public function broadcastWith(): array
    {
        return [
            'prices' => $this->prices,
            'updated_at' => now()->toIso8601String(),
        ];
    }
}
```

- کانال **عمومی** (نیاز به احراز هویت ندارد)
- نام event: `prices.updated`
- داده ارسالی: آرایه قیمت‌ها + زمان بروزرسانی

### ۴. API Endpoint

**آدرس**: `GET /api/price-board`

پاسخ نمونه:
```json
{
    "data": [
        {
            "Name": "طلا ۲۴ عیار",
            "Price": 4285000,
            "Change": "+۰.۸٪",
            "ChangePercent": 0.8
        }
    ]
}
```

### ۵. WebSocket Server (Reverb)

**package**: `laravel/reverb`

تنظیمات در `.env`:
```
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=962912
REVERB_APP_KEY=balggvnu3zobukks6mjy
REVERB_APP_SECRET=q4itsjf7f8u6ocja9fkr
REVERB_HOST=localhost
REVERB_PORT=8080
REVERB_SCHEME=http
```

برای اجرا:
```bash
php artisan reverb:start
```

### ۶. فرانت‌اند - هوک WebSocket

**فایل**: `src/lib/usePriceBoard.ts`

از کتابخانه `pusher-js` (پروتکل سازگار با Reverb) استفاده می‌کند:

```typescript
const pusher = new Pusher(REVERB_APP_KEY, {
    wsHost: REVERB_HOST,
    wsPort: REVERB_PORT,
    forceTLS: false,
    enabledTransports: ["ws", "wss"],
});

const channel = pusher.subscribe("price-board");
channel.bind("prices.updated", (data) => {
    setPrices(data.prices);
    setLastUpdate(new Date(data.updated_at));
});
```

**خروجي هوک**:
```typescript
{
    prices: PriceItem[];       // آرایه قیمت‌ها
    connected: boolean;        // وضعیت اتصال WebSocket
    lastUpdate: Date | null;   // زمان آخرین بروزرسانی
    fetchInitialPrices: () => void;  // دریافت قیمت‌های اولیه
}
```

### ۷. صفحه تابلو قیمت

**فایل**: `src/app/pages/PricesPage.tsx`

- کارت اصلی: نمایش قیمت طلای ۲۴ عیار + نمودار sparkline
- کارت‌های کناری: نقره و اونس جهانی
- جدول کامل: لیست تمام فلزات با قیمت، تغییرات و دکمه خرید
- نمایش وضعیت اتصال: سبز (متصل) / قرمز (قطع)
- لودینگ اولیه از API + بروزرسانی از WebSocket

---

## نحوه اجرا

### ترمینال ۱ - بک‌اند
```bash
cd /path/to/nopay-project

# اجرای سرور WebSocket
php artisan reverb:start

# اجرای Scheduler (هر دقیقه)
php artisan schedule:work
```

### ترمینال ۲ - فرانت‌اند
```bash
cd /path/to/zioto-new-site
npm run dev
```

### تست دستی Sync
```bash
php artisan priceboard:sync
```

---

## تغییرات اخیر مرتبط

| تاریخ | توضیح | کامیت |
|-------|-------|-------|
| اخیر | اضافه کردن ترجمه فارسی `(and :count more errors)` | `c5ea830` |
| اخیر | ارسال `temp_token` از verifyOtp به shahkarVerify | `650da65` |
