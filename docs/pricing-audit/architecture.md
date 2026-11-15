# Pricing architecture and data flow

## Boundaries

| Component | Responsibility | Evidence |
|---|---|---|
| `ApiClientService` | Provider fetch, field mapping, board formulas, fallback, trend, cache writes | `app/Services/Pricing/ApiClientService.php:15-27,171-238,245-435` |
| `PriceBoardService` | Facade for cached/fresh board | `app/Services/PriceBoardService.php:15-54` |
| `PricingSettings` / `Setting` | Typed reads, defaults, persistent values and cache invalidation | `app/Services/Pricing/PricingSettings.php:45-143`; `app/Models/Setting.php:28-56` |
| `DynamicPriceService` / `LaborCalculator` | Per-product calculation from board, weight, labor role/time, taxes, rounding | `app/Services/Pricing/DynamicPriceService.php:16-84,145-157,287-435`; `app/Services/Pricing/LaborCalculator.php:24-48,105-124` |
| `ProductResource` / `CartResource` | Current computed or stored price for API display | `app/Http/Resources/ProductResource.php:23-37`; `app/Http/Resources/CartResource.php:11-34` |
| `OrderSubmitController` | Recalculate cart prices, persist item breakdown and final total | `app/Http/Controllers/Api/OrderSubmitController.php:208-234,373-445,447-532` |
| `PaymentController` | Initiate gateway invoice from persisted order total | `app/Http/Controllers/Api/PaymentController.php:101-103,190-228` |
| `SyncPriceBoard` | Scheduled board refresh, history, product price persistence, broadcasts | `app/Console/Commands/Tokeniko/SyncPriceBoard.php:21-82,154-179`; `routes/console.php:5-9` |

There is no separate pricing repository layer in this path. Commerce currently owns pricing decisions and order persistence. MongoDB `PriceHistory` is observational history, not the live price source: `app/Services/PriceHistoryService.php:9-25,42-58`.

## Data flow

1. **Fetch:** scheduled `priceboard:sync` runs every minute with overlap protection. PersianAPI, Tala.ir, or optional Tokeniko board is requested (`routes/console.php:5-9`; `ApiClientService.php:171-228`).
2. **Map and select:** provider-specific response keys are mapped into internal `Gold750`, `Gold999`, `Gold705`, and reference-only `Silver999`; gold board items are selected/calculated; manual and coefficient settings participate (`ApiClientService.php:245-435,597-745`).
3. **Cache and fallback:** board payload is cached with configurable TTL; last successful computed data and raw provider data are persisted in settings, and per-source last-success times are tracked (`ApiClientService.php:61-106,813-859`; `PricingSettings.php:272-347`).
4. **Persisted catalog price:** scheduled sync calls `Product::calculatePrice()` for dynamic products and bulk updates `products.price` in a DB transaction (`SyncPriceBoard.php:154-179`; `Modules/Product/app/Models/Product.php:173-188`). This is the basic role price and has tax/rounding from the dynamic service.
5. **Display:** `ProductResource` and `CartResource` call `DynamicPriceService::priceFor()` at serialization time, falling back to `product.price` (`ProductResource.php:23-30`; `CartResource.php:11-23`). Product board key, weight, pack size, role, and period affect dynamic price.
6. **Order:** order submission obtains cart rows, recalculates price inside order-data creation, and snapshots product price, subtotal, board price and optional labor/taxes in `order_items`; order total includes shipping, discount and installment fee (`OrderSubmitController.php:100-103,373-445,447-532`). The surrounding order creation uses a DB transaction with up to three attempts (`OrderSubmitController.php:208-234`).
7. **Payment:** payment initialization creates the invoice and payment amount from `orders.total_amount`, not a fresh product-price lookup (`PaymentController.php:190-228`).

## Source-of-truth and lifetime

- Live board source for normal operation: cache payload `zioto:payload` (also legacy `priceboard:prices`), TTL `max(10, cache_duration_seconds)`; seeded default is 300 seconds (`ApiClientService.php:90-100,112-134`; `ZiotoPricingSettingsSeeder.php:35-40`).
- Last-known-good raw/base values and last-known-good final board are in `settings` keys `zioto_pricing_last_successful_raw_data`, `..._raw_time`, `..._last_successful_api_data`, and `..._last_successful_api_time` (`PricingSettings.php:272-347`).
- Product catalog `price` is stored in Rial and used as fallback/non-session/basic-role value; model cast is decimal scale 2 (`Product.php:63-87`).
- Order item fields are snapshots with decimal casts; `orders.total_amount` is decimal scale 0 (`Modules/Order/app/Models/OrderItem.php:33-45`; `Order.php:91-98`).
- MongoDB `price_history` records board/direct-price snapshots where the extension is installed; write failures are swallowed (`PriceHistoryService.php:42-58`).

No single transaction spans board cache writes, setting writes, product recalculation, API responses, and later payment. Product price persistence runs in a DB transaction after the cache has already been written. The order transaction snapshots a separate calculation; payment is a later operation.

## Tests

`GoldBoardPricingTest`, `SilverBoardPricingTest`, `DynamicPriceServiceTest`, `PriceBoardSyncTest`, and API tests cover selected branches. Examples include max/min and one-source fallback (`tests/Feature/Services/GoldBoardPricingTest.php:57-101`), manual prices and silver conversion (`SilverBoardPricingTest.php:44-95`), and sync persistence (`tests/Feature/Commands/PriceBoardSyncTest.php:77-110`). These are **VERIFIED BY TEST** as source assertions, not as tests run in this audit.
