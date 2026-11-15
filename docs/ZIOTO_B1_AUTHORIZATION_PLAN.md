# B1: Payment initiation authorization plan

## Decision

**Do not deploy the authentication change yet.** The backend fix is small, but this repository does not contain the production storefronts or an inventory of legacy callers. The current endpoint accepts anonymous requests, and the Nopay frontend has a separate platform and token context. Confirm those clients can send a valid platform-matched bearer token before implementation. This is a Category B behavior change, not part of the earlier Category A log patch.

This is a source-based design on branch `security/payment-hardening` (2026-11-11). No application source, previous uncommitted changes, production data or configuration was changed for this B1 task. `php artisan route:list --path=payment --except-vendor --no-interaction` was the only application command used. No provider call, migration or test was run.

## Current authority and risk

`routes/api.php:124-129` declares `POST /api/payment/init` without `auth.token`, while fee calculation and payment status are authenticated. `InitPaymentRequest.php:9-18` authorizes every request and validates only an integer, existing `order_id` and one of five gateway names. `PaymentController.php:100-105` then loads the order by client-supplied ID. It does not compare `order.user_id` with any authenticated principal. Before contacting the provider, it can fail an older pending attempt (`PaymentController.php:135-152`), then purchase externally and create a new attempt (`:201-235`). Therefore a known order ID can cause a provider payment session and change attempt state for another customer's order. It cannot debit a Zioto wallet because no such path exists here; some installment providers may exercise other financial authority when a request is created, so this must still be treated as a security boundary.

`Platform::fromRequest()` uses the caller-controlled `X-Platform` header and defaults missing/unknown values to `main` (`app/Support/Platform.php:27-38`). `TenantScope` filters `orders`, `payments` and `users` by that header (`app/Support/TenantScope.php:19-35`; `Modules/Order/app/Models/Order.php:23-25`; `Modules/Payment/app/Models/Payment.php:13-15`). The header prevents an accidental cross-platform query but is not authentication. Global order IDs are the target keys; the unique order number is scoped per platform, not the primary ID (`Modules/Order/app/Models/Order.php:32-59`; platform migrations). A caller can set `X-Platform: nopay` and submit a Nopay order ID today. Same phone/national code can correspond to different `users.id` values on `main` and `nopay` (`OtpController.php:97-99,146-170`; `TenantAuthFlowTest.php:270-323`), so identity must be bound to the exact order's `user_id` and `platform`, not matched by phone or national code. Some orders may have null `user_id` by schema (`Modules/Order/database/migrations/2026_06_15_000001_create_orders_table.php:13`); they cannot pass owner authorization.

The existing `auth.token` middleware reads a bearer token, hashes it, loads a user for the requested platform, and sets the `api` guard; missing/invalid/expired tokens return 401 with existing error codes (`app/Http/Middleware/AuthenticateApiToken.php:16-45`; `bootstrap/app.php:30`). OTP login or registration issues an API token (`OtpController.php:80-125,192-232`). A registration token is not yet an API bearer token. A main token with `X-Platform: nopay` is rejected; a Nopay token without that header defaults to `main` and is rejected. Existing authenticated checkout already uses the same middleware (`routes/api.php:66-70`), and order listing/show and payment status already restrict by `user_id` (`OrderSubmitController.php:38-59`; `PaymentController.php:605-619`). `config/auth.php:18-54` defaults to the `api` guard, but explicit `user('api')` is preferable in the new check if deployment overrides `AUTH_GUARD`.

## Caller and flow inventory

| Caller or flow | Repository evidence | B1 compatibility conclusion |
| --- | --- | --- |
| Main storefront | `POST /api/orders` requires `auth.token` (`routes/api.php:66-70`), returns an order ID (`OrderSubmitController.php:243-252`). No storefront implementation of `/payment/init` is present in `resources/js`, `Modules/*/resources/assets/js`, or other first-party JS/TS/Vue files. Main frontend URL is separately configured (`config/app.php:126`). | The client should possess an API token at order creation, but whether it retains/sends that token at payment initiation and retry is unknown. |
| Nopay installment landing | Nopay is a separate platform (`Platform.php:11-15`); the configured frontend URL is distinct (`config/app.php:128`). Nopay init requires eligible products and a special callback URL (`PaymentController.php:154-181`); the bare callback remains public (`routes/api.php:126`). `NopayProductEligibilityTest.php:273-300` calls init anonymously. | A Nopay client must send its **Nopay** bearer token and `X-Platform: nopay`. A main token or missing header will fail. The landing implementation is absent, so compatibility is unverified. |
| Digipay, Smartis, Kamanlend installment checkout | `InstallmentService.php:7-29` classifies these as fee gateways; order creation chooses installment payment method and fee (`OrderSubmitController.php:500-528`). They all enter the same init controller (`InitPaymentRequest.php:18`; `config/payment.php`). `docs/kamanlend-payment-flow.md:51-63` describes a frontend call to init but does not show auth headers. | No provider-specific auth exception is justified by repository code. Confirm each actual client sends bearer and correct platform before rollout. Gateway callbacks are separate public routes and must stay public. |
| Parsian standard checkout | Shares init route and controller (`routes/api.php:125`; `PaymentController.php:100-235`). | Same owner-token requirement; client implementation unknown. |
| Tapsi external flow | Tapsi has a separate webhook (`routes/api.php:85-88`; `TapsiWebhookController`), and no repository call from it to payment init was found. | Do not change Tapsi. Confirm no external Tapsi-operated client calls init before rollout. |
| Postman/manual clients | `Zioto-API.postman_collection.json` has an Init Payment request; collection root has bearer auth, while the request itself declares no auth override. Its description and gateway list are stale relative to current route/validation. | The collection likely sends inherited bearer auth, but this is not proof about scripts or deployed clients. |
| Existing tests and legacy clients | All eight `PaymentControllerInitTest.php:27-292` init calls and `NopayProductEligibilityTest.php:273-300` use no bearer. These tests codify current anonymous behavior, not proof of a production requirement. No other non-test caller of `/payment/init` was found in repository search. | Tests must be updated for approved behavior. Any external mobile, older storefront, retry link, or support tool that sends only order ID/gateway will receive 401. Inventory is unavailable in this repo. |

The search covered first-party PHP, JS/TS/JSX/TSX/Vue, route files, tests, Markdown and the Postman collection while excluding vendor, generated Filament assets, database dumps and `node_modules`. Absence of a caller here does **not** prove absence of deployed clients or a separate frontend repository.

## Proposed smallest application diff after client verification

1. Add `auth.token` **only** to `POST /payment/init` in `routes/api.php:125`. Do not put it on the `payment` prefix group: Parsian, Digipay, Kamanlend, Smartis and Nopay provider callbacks at `:126-127` must stay public with their current GET/POST methods and URLs.
2. In `InitPaymentRequest.php:14-18`, keep `order_id` and `gateway` names and the existing 422 validation shape. Replace the unscoped `exists:orders,id` with an existence rule constrained to the authenticated `api` user's ID **and platform**. This gives the same `order_id.exists` response for an unknown or unauthorized ID, so the validation result does not reveal whether another customer's order exists.
3. In `PaymentController.php:104`, load the order with the same explicit `user_id` and `platform` predicates as defense in depth. All payment creation, Nopay eligibility, purchase payload, callback URL, 200 response (`payment_url`, `transaction_id`) and provider callbacks remain byte-for-byte at their current contracts. No gateway or order-status rule changes.

Illustrative diff (not applied):

```diff
 // routes/api.php, inside Route::prefix('payment')
-Route::post('/init', [PaymentController::class, 'init']);
+Route::post('/init', [PaymentController::class, 'init'])->middleware('auth.token');

 // app/Http/Requests/InitPaymentRequest.php
+use Illuminate\Validation\Rule;
 // inside rules():
-'order_id' => ['required', 'integer', 'exists:orders,id'],
+'order_id' => ['required', 'integer', Rule::exists('orders', 'id')
+    ->where('user_id', $this->user('api')->getAuthIdentifier())
+    ->where('platform', $this->user('api')->platform)],

 // app/Http/Controllers/Api/PaymentController.php, inside init():
-$order = Order::with(['user', 'address'])->findOrFail($validated['order_id']);
+$order = Order::with(['user', 'address'])
+    ->where('user_id', $request->user('api')->getAuthIdentifier())
+    ->where('platform', $request->user('api')->platform)
+    ->findOrFail($validated['order_id']);
```

The route middleware must execute before form validation, as on existing authenticated order routes. Validate this in feature tests; do not use `auth:sanctum` or the global default guard as a substitute for the existing platform-aware `auth.token` middleware. The `Rule::exists()->where()` API is available in the installed Laravel framework. If a legitimate flow creates `orders.user_id = null`, it needs a separately approved, short-lived signed order-bound payment intent or authenticated service authority; an order ID, platform header, phone, order number or unrestricted server token is not an acceptable bypass. No such new mechanism is included in the minimal diff.

## Regression tests to add with an approved implementation

Use `RefreshDatabase` or the project's established isolated SQLite `:memory:` setup from `phpunit.xml`, mocked Multipay purchase, and `Queue::fake()` only where an assertion needs it. Do not contact a provider. Extend `tests/Feature/Api/PaymentControllerInitTest.php` and `NopayProductEligibilityTest.php`; preserve existing assertions by supplying a platform-matched bearer token for their order owners. Run each changed file alone, then the affected auth/tenant tests.

| Scenario | Expected result and side-effect assertion |
| --- | --- |
| Owner on `main`, each approved gateway | 200 with unchanged `payment_url`/`transaction_id` keys; one pending attempt linked to that order/user/platform; mocked provider purchase called once. Also test existing pending/failed retries with owner token. |
| Owner on `nopay` with `X-Platform: nopay` | Nopay eligible order follows existing result/callback URL; no new requirement on the provider's bare callback. |
| Different user on same platform | `order_id` validation error (422, existing message); provider purchase never called; existing pending attempt stays unchanged; no new attempt or order update. |
| Missing, invalid or expired bearer | Existing `TOKEN_MISSING`, `TOKEN_INVALID`, `TOKEN_EXPIRED` 401 as applicable; no payment mutation or provider purchase. |
| Main token with Nopay header; Nopay token without header | 401 from platform-aware middleware; no mutation. Confirm client can send correct header before rollout. |
| Same real-world phone/national code but separate platform accounts | Nopay user cannot initiate main user's order, and vice versa; 401 for mismatched token/header or 422 for an order outside the authenticated platform; no mutation. |
| Nonexistent order ID and another user's existing ID | Same 422 `order_id` validation contract, avoiding an order-existence oracle. |
| Null `user_id` order | Cannot initiate as an ordinary customer; record whether any legitimate external/legacy path creates such an order and requires separate design. |
| Provider callback regression | Existing GET/POST callback and Nopay bare callback tests pass without bearer; init route middleware is not inherited by callbacks. |

The happy-path test should assert provider-facing amount, callback URL and invoice identity fields are unchanged. Existing tests currently call a real gateway on some paths after passing validation, so the approved update must mock every successful purchase path rather than merely asserting a non-422 status. Run `php artisan test --compact` with specific files/filters, then `vendor/bin/pint --dirty --format agent` for changed PHP files. Do not infer concurrency safety from SQLite; B2 remains separate.

## Compatibility gate, rollout and rollback

Before implementation, obtain the deployed main storefront and Nopay landing source or request traces from their owners, plus a list of mobile, older storefront, partner, support and direct API clients. For each, confirm whether init sends `Authorization: Bearer <api token>`; whether it sends `X-Platform` consistently with order creation; how it stores/refreshes a 72-hour token; whether retry/payment links initiate payment from a new browser; and whether any guest or null-owner order uses this endpoint. Check all five gateways because a client may choose an installment gateway after an earlier order request. Inspect only metadata or privacy-safe aggregate telemetry; do not collect raw tokens or customer records in logs.

Roll out client changes first while the server still accepts the old request, verify behavior in an isolated/staging environment with mocked or provider sandbox integrations under separate authorization, then deploy the endpoint-only gate after client adoption is established. Monitor 401/422 rates by platform and gateway, successful init rate, and checkout abandonment without logging credentials. Preserve public callback routes and the existing request/response bodies. If a client cannot supply the owner token, design a separately authorized payment intent before tightening this endpoint; do not add an anonymous `order_id` exception.

Rollback of the proposed code change would restore anonymous attempt creation and its known abuse path. Treat that as an emergency, time-limited availability measure only after security review, pause any future wallet use of this route, and reconcile attempts created during the interval. A safer launch gate is to defer the server change until every legitimate client is ready. No production-safe or zero-side-effect claim is possible from source inspection and isolated tests alone.
