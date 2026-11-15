# Formulas and numeric behavior

## Notation and units

`P` denotes provider values after code mapping; board values are intended to be Rial per gram. `M750`, `M999`, and `MSilver` are admin manual entries in Toman per gram, converted to Rial with ×10. Coefficients are unitless decimal multipliers. Source code is `app/Services/Pricing/ApiClientService.php:245-435`; decimals use `BcmathHelper` scale 8 by default (`app/Services/Pricing/BcmathHelper.php:8-105`). Formulas below do not imply physical purity ratios.

## Gold 750

For positive source values `P750` and `T750` (Tala already ×10):

- `maxApiGold750 = max(P750, T750)` when both exist, otherwise the one positive source, otherwise null (`ApiClientService.php:271-278`).
- `gold750Sell = (M750 × 10 if set, else maxApiGold750) × coef_gold750_sell_ratio` (`:280-287`).
- `minApiGold750 = min(P750, T750)` when both exist, otherwise one positive source (`:289-302`).
- `gold750Buy = gold750Sell × coef_buy_price` if manual price is set or the two source prices are equal; else `minApiGold750 × coef_gold750_buy_ratio`; if that is unavailable and sell exists, sell × `coef_buy_price` (`:304-311`).

The code labels outputs `sell` and `buy`, but does not define whether that is from the customer’s or company’s perspective. That business meaning is **UNKNOWN**.

## Gold 999 and derived 995 / 999.9

- `gold999Sell = (M999 × 10 if set, else maxApiGold999) × coef_gold999_sell_ratio` (`:313-340`).
- `gold999Buy = minApiGold999 × coef_gold999_buy_ratio`; when manual is set or both provider values compare equal, the code replaces it with `gold999Sell × coef_buy_price`; if no min exists but sell exists, same fallback (`:342-363`).
- `gold995Sell = gold999Sell × coef_gold999_to_gold995 × coef_gold995_sell_ratio`.
- `gold995Buy = gold999Buy × coef_gold999_to_gold995 × coef_gold995_buy_ratio`.
- `gold9999Sell = gold999Sell × coef_gold999_to_gold9999 × coef_gold9999_sell_ratio`.
- `gold9999Buy = gold999Buy × coef_gold999_to_gold9999 × coef_gold9999_buy_ratio` (`:365-378`).

The selector applies min/max to provider mapped values before the buy/sell coefficient. Fineness conversion is subsequently applied only for 995/999.9 derivation from 999. These are configurable business multipliers; they are not computed as nominal purity ratios. A real reversal property is not tested or guaranteed.

## Silver

- `silver9999Sell = MSilver × 10`, if a positive manual value exists; otherwise no Silver9999 sell entry.
- `silver999Sell = silver9999Sell × coef_silver9999_to_silver999`.
- `silver9999Buy = silver9999Sell × coef_silver_buy_price`.
- `silver999Buy = silver999Sell × coef_silver_buy_price` (`:380-402`).

Persian `Silver999` is debug/reference only. Tala silver keys are requested in config but not used by `mapTalaItems` (`ApiClientService.php:728-745`).

## Admin defaults (seed values, not live settings)

`ZiotoPricingSettingsSeeder.php:15-27` seeds: `coef_gold999_to_gold995=0.995996`, `coef_gold999_to_gold9999=1.005`, `coef_buy_price=0.99`, Gold999 sell/buy `1.001/0.997`, Gold995 sell/buy `1/0.995`, Gold9999 sell/buy `1/0.995`, silver conversion/buy `0.961538/0.99`, emergency multipliers `1/1`. Gold750 ratios are absent from this seeder excerpt but `PricingSettings::COEFFICIENT_DEFAULTS` provides `1.001/0.997` (`PricingSettings.php:27-43`). These are code defaults and do not reveal production setting values.

## Product price formula

Given board price `boardPrice` in Toman/gram (board Rial ÷10), weight `weight` in grams, labor coefficient `c`, pack size `n`:

`base = weight × boardPrice`; `labor = base × c`; pre-tax price = `base + labor`; taxes and configured rounding are then applied; multiply per-item result by pack size; `priceFor()` multiplies by 10 to return Rial (`DynamicPriceService.php:72-83,145-157,287-327`).

In non-reference rounding mode, source uses floats: `round(weight × boardPrice × (1+c))`, applies tax (PHP `round`), then optional nearest-step rounding (`DynamicPriceService.php:289-295,358-435`). In `reference` mode it uses BCMath scale 10 for base/labor/tax math; silver is ceiled to `silver_round_to` before metal tax; gold labor VAT is applied on labor; gold is ceiled to `round_to` after taxes (`:297-327,340-347`). Configured rounding off means no step rounding; note non-reference branch still uses ordinary `round()` on the raw product amount.

`tax_gold`/`tax_silver` apply to the whole calculated amount when positive. `tax_gold_labor` applies to labor only in reference mode and is added atop price; in non-reference mode `applyLaborTax` adds `labor × rate` on top of the already-computed amount (`DynamicPriceService.php:307-327,350-415`). Per-product stored `fee_off_hours` and `fee_business_hours` exist but the reviewed dynamic formula uses `labor_coefficients`, not those fee fields.

## Synthetic example

Assume synthetic Persian Gold999 `100,000,000 Rial/g`, Tala `99,000,000 Toman/g` → `990,000,000 Rial/g` after ×10. With seeded `coef_gold999_sell_ratio=1.001` and `coef_gold999_buy_ratio=0.997`, and no manual override:

- Sell base: max = 990,000,000; sell = 990,990,000 Rial/g.
- Buy base: min = 100,000,000; buy = 99,700,000 Rial/g.
- `gold995Sell = 990,990,000 × 0.995996 × 1 = 987,022,076.04 Rial/g` (before conversion to JSON float).

The synthetic values intentionally demonstrate the algorithm, not actual quotes. This example also illustrates that unit inconsistency in live source data would materially distort selection; provider semantics require validation.

## Numeric audit

- Board calculations use decimal strings/BCMath where available, scale 8 by default; absent BCMath falls back to binary floats (`BcmathHelper.php:17-62`). Final board values are cast to float (`ApiClientService.php:438-456`), then board JSON exposes floats.
- Product calculation uses PHP floats for weight, roles, price API, taxes and total return; reference mode converts float inputs to decimal strings before BCMath (`DynamicPriceService.php:65-83,287-338`). `products.price` has decimal:2 cast; order totals and product prices are integer-like in API/payment paths (`Product.php:63-87`; `Order.php:91-98`).
- Rounding may occur at multiple points: BCMath truncation to scale (not rounding), whole-unit PHP `round()`, optional step rounding, pack multiplication, integer casts in API/order paths. A single universal rounding rule does not exist.
- Weight input supports seven decimal places in product schema/cast; formulas use grams. No milligram conversion is seen in the pricing path.
- Currency conversion convention is Rial board/product stored prices versus Toman admin board inputs and Toman calculation inputs, converted to Rial at `priceFor` return. External Persian denomination semantics and provider timestamp semantics remain **UNKNOWN**.
- Overflow limits are **UNKNOWN**; product and order values are floating-point in parts of the pipeline.
