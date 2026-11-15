# External price providers

No live provider requests were made. Provider contract descriptions below are based on repository configuration, parser code, comments, and fixtures; the upstream API’s real-time contract and semantic definitions remain unverified.

## PersianAPI

- **Purpose:** external gold market input, apparently TGJU data; one mapped silver field is retained for reference/debug but does not price silver board entries (`ApiClientService.php:29-34,400-401`).
- **Configuration:** `config/zioto-pricing.php` keys `persian_api.enabled`, `.url`, `.token`, env names `ZIOTO_PERSIAN_API_ENABLED`, `ZIOTO_PERSIAN_API_URL`, `ZIOTO_PERSIAN_API_TOKEN` (lines 11-15). Credentials are intentionally not reproduced here.
- **Transport:** Laravel HTTP client, 15 second timeout, two attempts with 200ms delay, `Accept: application/json`, bearer token when configured (`ApiClientService.php:597-607`).
- **Response fields used:** `result[]` entries with `key` and `price`; key IDs map to `Gold705`, `Gold750`, `Gold999`, `Silver999` (`ApiClientService.php:29-34,620-628,698-722`). Duplicate mapped entries retain the max price (lines 715-718).
- **Units:** parser passes price strings through unchanged. The application then compares them against Tala values which it multiplies by 10. Thus Persian values are assumed to already use the internal Rial-per-gram unit; exact upstream denomination is **UNKNOWN** without provider documentation/response evidence. The code does not normalize Persian values.
- **Timestamp/staleness:** provider item timestamps are not read. Local fetch time is used as source success time (`ApiClientService.php:801-810,824-827`).
- **Malformed/error:** connection exceptions, failed HTTP, or missing/invalid `result` return an empty source result after logging (`ApiClientService.php:608-628`). No schema validation beyond expected keys and numeric-positive checks downstream.

## Tala.ir

- **Purpose:** external gold market input. Config keys are `tala_api.enabled`, `.token`, `.cookie`, `.base_url`, `.keys` (`config/zioto-pricing.php:23-29`).
- **Transport:** 15 second timeout, two attempts/200ms, JSON accept, optional cookie; request token is added as a URL query parameter. **TLS verification is disabled** (`ApiClientService.php:631-650`).
- **Response fields used:** if `success` exists and is not boolean `true`, response is rejected; parser accepts `rates` or root array and maps `geram18k.value` to `Gold750`, `bazartehran.value` to `Gold705`, `geram24k.value` to `Gold999` (`ApiClientService.php:663-691,728-745`). Each mapped value is multiplied by 10.
- **Units:** application intends Tala `value` to be Toman per gram because it multiplies by 10 before comparing with Persian values and stores the board in Rial. This expectation is code-derived, not confirmed against a live provider contract.
- **Timestamp/staleness:** provider timestamps are ignored; local success time is recorded.
- **Malformed/error:** missing token returns `[]`; network/HTTP/empty/non-array or unsuccessful responses are logged and treated as absent (`ApiClientService.php:636-638,651-691`). `mapTalaItems` checks only field existence before multiplying; nonnumeric values can reach arithmetic helper fallback behavior.

## Optional Tokeniko board and product sources

- **Tokeniko board:** enabled by DB setting `zioto_pricing_tokeniko_board_enabled` (default false). On a nonempty accepted response, it becomes the board source and Persian/Tala are marked disabled for that fetch. It reads `products` or `data.products`, only names in an allowlist, and `sellPrice`/`buyPrice`; it uses values directly as Rial (no manual override or conversion coefficient) then emergency multiplier (`ApiClientService.php:180-201,518-566,576-594`). On error/unusable data it falls back to computed Persian/Tala board.
- **Tokeniko product prices:** separate DB setting `zioto_pricing_tokeniko_product_prices_enabled` (default false) and product `tokeniko_sku`; direct shop price is returned before dynamic-pricing toggle/eligibility (`DynamicPriceService.php:50-58,94-108`). Service `TokenikoShopService` should be inspected alongside this source when changing its response contract; the board source is not the same endpoint/feature.
- `config('pricing.mode')` also supports `direct`, where scheduled sync runs a separate Tokeniko direct sync (`SyncPriceBoard.php:31-39,230-257`).

## Comparability and fallback conclusion

For gold 750 and 999, code normalizes Tala by ×10 and leaves Persian data unchanged, then compares values. The architecture assumes same metal, fineness and price unit after this normalization; whether provider fields truly have identical semantics is **UNKNOWN**. APIs are not both required: source branches accept one positive source as a usable base. Fineness differs by base (`geram18k` is 750, `geram24k` is mapped as 999) and derived board keys use configured multipliers.

Source disagreement has no threshold/guard: max/min selection intentionally uses both positive values however far apart. No age timestamp from provider is used to establish quote freshness.
