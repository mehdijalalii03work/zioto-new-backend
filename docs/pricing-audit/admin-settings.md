# Admin settings, overrides, and audit

Settings are generally rows in `settings` (`key`, `value`, `type`, `category`, `label`, etc.); `Setting::getValue()` caches values forever until saved/deleted hooks clear that key (`app/Models/Setting.php:8-25,28-56`). Seeded defaults are defaults for a fresh/seeder-run database, not evidence of current production values.

## Key setting groups

| Key(s) | Meaning/type/default evidence | Validation/authorization/effect |
|---|---|---|
| `zioto_pricing_manual_gold750_sell`, `...manual_gold999_sell`, `...manual_silver9999_sell` | Numeric Toman/gram, default empty in seeder (`ZiotoPricingSettingsSeeder.php:30-33`) | Numeric nullable input; no explicit maximum/minimum. View `pricing.view`, save `pricing.edit`; cache invalidated. Sell base overrides gold provider base; silver is sole sell input. `ManualPricesPage.php:34-107`. |
| `zioto_pricing_coef_*` | Number multipliers; defaults in `PricingSettings::COEFFICIENT_DEFAULTS` and seed list | Numeric input; most ratios step 0.0001, some conversion inputs numeric only; no range bounds. `pricing.edit`; cache invalidated. Board only. `CoefficientsPage.php:59-65,143-180`. |
| `zioto_pricing_persian_api_enabled`, `...tala_api_enabled`, `...tokeniko_board_enabled` | Boolean; seeded true/true/false | Toggle; `pricing.edit`; cache invalidated. Changes source usage and board values. `PricingSettingsPage.php:68-79,96-127`. |
| `zioto_pricing_cache_duration_seconds`, `...stale_max_age_seconds`, `...stale_block`, `...trend_threshold` | Integer/boolean; defaults 300/21600/false/100 | Numeric controls have no visible minimum/maximum in this page; service clamps cache to ≥10, stale age ≥0, threshold ≥1. Affect cache/fallback/trend, not direct product overrides. `PricingSettingsPage.php:39-64,104-127`; `PricingSettings.php:126-143`. |
| `zioto_pricing_emergency_mode_enabled`, `...coef_emergency_gold`, `...coef_emergency_silver` | Boolean and multipliers; seeded false, 1, 1 | Toggle/numeric, no coefficient bounds; `pricing.edit`; final board values multiplied by metal-specific factor. |
| `zioto_pricing_enable_dynamic_pricing` | Boolean default true | Product-level dynamic calculation feature; default `priceFor` returns null if disabled unless Tokeniko direct price succeeded first (`PricingSettings.php:70-78`; `DynamicPriceService.php:50-58`). |
| `zioto_pricing_tokeniko_product_prices_enabled` | Boolean default false | If enabled and product has a valid `tokeniko_sku` response, price returned before dynamic toggle and eligibility checks (`DynamicPriceService.php:50-58,94-108`). |
| Product `price_type`, `price`, `price_board_item`, `weight`, `pack_size`, `labor_coefficients` | Stored product fields; `price_type` fixed/dynamic; admin price uses Rial; weight grams | Pricing form gated by `product.pricing`; dynamic price input is disabled/dehydrated off; board key selected for dynamic path. Validation includes min bounds for `price`, `pack_size`; `ProductForm.php:143-233`. |
| `tax_gold`, `tax_silver`, `tax_gold_labor` | Percent values; metal taxes default to 0 at reads, labor tax default 0 | Labor tax UI allows 0–100; only dynamic price formula. `PricingSettings.php:115-124`; `ProductSettingsPage.php:114-121`. |
| `zioto_pricing_labor_roles`, `...time_periods`, per-product `labor_coefficients` | JSON role/time structures, matrix defaults 1.0 when no matrix | Role and period editors gate save by `pricing.edit`; validate nightly period presence, but not all row values/ranges. Product coefficient is float, negative clamps to zero (`LaborSettingsPage.php:105-155`; `LaborCalculator.php:75-113`). |
| `zioto_pricing_round_prices`, `...rounding_mode`, `...round_to`, `...silver_round_to` | Boolean, enum, integer values; defaults false/nearest/1000/10000 | UI constrains rounding mode to nearest/reference and steps ≥1. Used by product dynamic calculator, not raw board calculations (`ProductSettingsPage.php:95-113`). |

The seeder includes additional sales control keys and default values in `database/seeders/ZiotoPricingSettingsSeeder.php:42-63`. Some are not price-setting inputs but affect order acceptance and labor selection.

## Product-specific and mode precedence

There are distinct modes; one universal ordering is misleading:

1. On product `priceFor()`, an enabled valid Tokeniko product price is returned first.
2. Otherwise, if global dynamic pricing is disabled, return null and caller uses stored product `price`.
3. Otherwise, eligible dynamic product with a `price_board_item` uses current board value and labor/tax/rounding formula.
4. Missing board key/value or ineligible product returns null and caller uses stored `price`.

Board source precedence in normal mode:

1. If Tokeniko board toggle on and usable Tokeniko data exists, direct Tokeniko board values are used; if it fails, computed source board fallback proceeds.
2. In computed board mode, each enabled provider is fetched; prior successful raw provider data may substitute for unavailable current data unless stale blocking applies.
3. Positive manual gold base overrides either provider-based gold sell base. Silver9999 manual entry is the only supported Silver9999 sell base.
4. Final configured coefficients apply; emergency multipliers apply last.
5. If no current/fallback source-derived board prices survive, last successful final board is used unless stale policy blocks it.

Exact branches: `ApiClientService.php:180-237,245-435,813-859`; product price ordering: `DynamicPriceService.php:50-83,94-108`.

## Authorization, audit, approval

Pricing pages require `pricing.view` for access and separately check `pricing.edit` on save (`ManualPricesPage.php:34-36,82-88`, equivalent checks in coefficient/settings pages). Product pricing form is shown to users with `product.pricing` (`ProductForm.php:147`). Audit observers write `price_audit_logs` for product price fields and selected settings; records include actor id/name/IP and old/new changes (`PricingSettingAuditObserver.php:31-65`; `ProductPricingAuditObserver.php:62-79`; migration `2026_10_19_000000_create_price_audit_logs_table.php:11-23`).

No approval/review workflow is evidenced. The observer allowlist omits some board controls, including cache/stale/trend settings and `tokeniko_product_prices_enabled`; edits to those keys are not necessarily audited by this observer (`PricingSettingAuditObserver.php:46-65`). Observer writes are not shown in the same explicit transaction as all pricing changes. Product update statements in sync use query-builder bulk update and therefore bypass model observers (`SyncPriceBoard.php:173-177`), so automated repricing itself is not captured as product-edit audit entries.
