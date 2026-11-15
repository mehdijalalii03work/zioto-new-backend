# Zioto payment hardening plan

## Scope and evidence

Static inspection of the current `security/payment-hardening` branch on 2026-11-11. The existing discovery report is untracked user work and is preserved. This plan concerns the production commerce backend only. No live database, provider, or customer data was queried. Installed versions: Laravel 13.35.0, Multipay 2.11.0, PHPUnit 12.5.38. Deployment DB driver, merchant contracts, client traffic, and schema drift remain unverified.

## Current lifecycle and dependencies

| Stage | Current behavior and source | Side effects |
| --- | --- | --- |
| Checkout | Authenticated `POST /api/orders` checks cart, identity, stock if reservation is enabled, discount, shipping, dynamic pricing, and may reuse a pending order (`routes/api.php`; `OrderSubmitController::submitOrder`, `calculateOrderData`, `findExistingSimilarOrder`). | Transaction creates order/items/shipping and reserves discount. Reuse updates total, shipping and notes but leaves item snapshots unchanged. Identity and pricing may call external services before the transaction. |
| Payment initiation | Public `POST /api/payment/init`; form request authorizes all requests; order is loaded by supplied ID with no owner check (`routes/api.php:125`; `InitPaymentRequest`; `PaymentController::init`). | Rejects paid/cancelled/expired orders and processing attempts; marks older pending attempts failed; checks Nopay eligibility and stock; Multipay purchases externally, then purchase callback inserts pending attempt. Returns payment URL and transaction ID. An unauthenticated caller can create an attempt for another order. No wallet debit exists here. |
| Callback | Public GET/POST `/api/payment/callback/{orderId}/{gateway}` and bare `/api/payment/callback` for Nopay. `PaymentController::callbackByToken` resolves `tv`/`Token`/`token`; `callback` resolves token/uuid or newest matching pending/processing attempt. | Short transaction locks attempt and moves pending to processing. Provider verification is outside transaction. Successful verification then updates attempt paid and order confirmed in a separate transaction, writes note, clears cart; a failed transaction triggers quiet best-effort paid writes. Failed verification marks attempt and order failed and writes note. |
| Confirmation | `AppServiceProvider` registers Hesabfa, discount, stock and SMS observers. | Order update synchronously runs Hesabfa contact/invoice calls (and nested DB work), redeems discount, conditionally increments reserved stock, queues received-health SMS. Controller queues confirmation and received-health SMS after persistence. `OrderSmsNotificationService` checks sent markers, but simultaneous dispatches can both observe unsent markers. |
| Expiration | `orders:cancel-unpaid` runs for pending orders older than 20 minutes with pending/failed payment status and no processing attempt (`CancelUnpaidOrders`; `routes/console.php`). | Loads candidates then updates individually without a row lock or recheck; cancellation releases discount through observer. It can race with callback's short processing transition or finalization. |

Order total is item subtotals plus shipping/tax minus discount plus any installment fee (`OrderSubmitController::calculateOrderData`). Multipay purchase and verification use the current `order.total_amount`; the attempt stores that amount at purchase (`PaymentController::init`, `callback`). `findExistingSimilarOrder` compares product IDs, quantities and integer unit prices, then recalculates the total and shipping without rewriting item snapshots. It does not reject an outstanding pending attempt. Exact monetary equality across persisted snapshots and attempts therefore is not enforced. Rials are used by the gateway config; the success note displays tomans by division by ten.

## Gateway compatibility map

| Gateway | Callback and verification | Compatibility concern |
| --- | --- | --- |
| Parsian | GET/POST order/gateway callback; `Token`/`token`, status `0` required before Multipay verify. | Nonzero status is a failed attempt; preserve redirect and status handling. |
| Digipay | GET/POST order/gateway callback; transaction ID can fall back to newest attempt. Custom driver verifies provider response. | Existing callbacks may omit a token; cannot require one without provider contract review. |
| Kamanlend | GET/POST order/gateway callback; driver sends terminal credentials and national code, verifies sale state with token. | Purchase and verify log contexts currently expose credentials/identity. Keep payloads sent to provider unchanged. |
| Smartis | GET/POST order/gateway callback with `uuid`; driver checks returned amount and confirms with provider. | Provider confirmation happens before local finalization, so local failure needs reconciliation. |
| Nopay | GET/POST bare callback using `tv`/`Token`/`token`, or configured exact return URL; hosted installment redirect. | Merchant panel requires exact bare URL. Keep route and token parsing unchanged. |

All use the shared callback controller and Multipay 2.11.0. Merchant ID/amount checks beyond the inspected driver code require provider contract and integration review; do not infer them from a callback parameter alone. Tapsi has a separate authenticated/signature-dependent webhook projector and should be regression checked if order finalization changes.

## Findings, classification and proposed changes

| ID | Category / severity | Evidence and existing behavior | Proposed outcome, impact, rollback |
| --- | --- | --- | --- |
| A1 | A / medium-high | `PaymentController::init` logs buyer phone/national code; callback logs raw query/post and unknown bare token; verify success logs raw receipt details. `Kamanlend::purchase` logs payload containing `terminalSecret`, `customerNationalCode`; verify logs national code and full provider body. | Remove sensitive fields from log context while retaining order/payment IDs, gateway, status and safe response status. No gateway request, API schema or DB change. Roll back the local log-context diff if operations need a missing diagnostic field; use stored attempt/receipt IDs for reconciliation. Tests assert secrets absent and correlation metadata present. |
| B1 | B / critical | Public init plus `authorize(): true`, no owner check; current tests call it without credentials. | Authenticate and authorize order-bound attempts after auditing storefront, Nopay and other client callers. A supplied order ID, platform or phone is not authority. Existing clients may receive 401/404; migrate them to a short-lived authenticated payment intent. Files: route, request/controller, client contracts, tests. Rollback requires an explicitly time-limited compatibility path, not restored arbitrary authority. |
| B2 | B / high | Callback lock ends before verification; final paid update has no locked order/attempt terminal recheck. Failed verification writes order failed from a stale model. | Introduce atomic, order-scoped finalization and explicit terminal conflict outcomes after external verification, without holding a DB transaction across provider calls. Requires per-gateway replay semantics, concurrency tests on deployment DB, observer idempotency review. Files: callback, cancellation command, tests; no new status assumed. Rollback to prior release only with reconciliation of in-flight attempts. |
| B3 | B / high | `CancelUnpaidOrders` candidate query and per-row update are separate; callback can verify after cancellation and currently confirm cancelled order. | Define paid-after-cancel policy with stock/discount checks and operator/refund path. Then make cancellation compare-and-set against current order and attempt state. Scheduled behavior and customer outcome change; rollback needs exception review. |
| B4 | B / high | Pending-order reuse rewrites total/shipping without item snapshots (`findExistingSimilarOrder`); attempt amount may also become stale. | Freeze or coherently refresh quote and attempt amount according to approved price and fee policy. Affects historical display, checkout totals and provider amount; test metal repricing, shipping, discounts, installment rounding. Do not recalculate paid orders. |
| B5 | B / high | Stock observer logs an insufficient conditional increment and continues; successful payment remains confirmed. Availability is controlled by `hesabfa.enable_reserved_stock` and `Product::ignoresReservedStock()` setting. | Decide fulfillment/refund policy and reserve atomically before a capture decision, respecting the dynamic reserved-stock setting. Would change confirmation and Hesabfa behavior; requires staged rollout and stock reconciliation. |
| C1 | C / high | After provider verification succeeds, finalization may fail; quiet saves can fail too, leaving only a critical log (`PaymentController::callback`). | Durable reconciliation record/workflow with provider status lookup, dedupe and operator exceptions. Requires persistence/design, provider re-verification contracts and schema approval. No queue-only workaround. |

Category A1 is safe for automatic implementation. B/C require explicit approval and a separately reviewed deployment sequence. B2, B3 and C1 must be designed together around verified-but-unpersisted payment, duplicate callback and cancellation outcomes. No new statuses, migrations, scheduling, queue or dependency changes are in this plan's automatic scope.

## Regression test matrix

| Area | Required cases | Current coverage / next gate |
| --- | --- | --- |
| Authorization | Owner, other user, missing/invalid token, cross-platform, legacy hosted installment flow | B1 gate; existing init tests explicitly use anonymous calls. |
| Callback | Duplicate sequential/concurrent, paid/cancelled/expired, failed verify, verified/local failure, repeated transaction ID | Existing `PaymentControllerCallbackTest` covers sequential duplicate, failed verify, quiet fallback and Nopay bare token; concurrency/terminal policy needs B2/C1 gate and a DB supporting row locks. |
| Attempts | Same/two providers, pending vs processing, failed retry, transaction uniqueness | Existing init test covers processing and failed retry; cross-provider concurrency needs B2. |
| Pricing | Stable/dynamic price, pending reuse, snapshots, discount, shipping, installment fee, rial rounding | Existing order reuse/fee tests; coherent snapshot behavior requires B4 approval. |
| Inventory/side effects | Short stock, concurrent checkout, reservation/release, discount, SMS and Hesabfa dedupe | Existing stock and observer tests; policy and concurrency need B5/B2. |
| Expiration | Unpaid, processing, late success, simultaneous callback/cancellation | B3 gate; run on deployment-equivalent DB before release. |
| Log minimization | Purchase/callback secrets absent, correlation IDs present, all gateway response contracts unchanged | Add focused A1 tests using mocked gateway/logger, no external requests. |

## Deployment and rollback

A1 deploys with no schema or configuration change. Verify payment logging still contains correlation IDs and provider status codes in a non-production environment, then review log access and retention. Historical logs already written may contain secrets and PII; rotation/access review is an operations task outside this code patch. Do not connect this task to production services. Rollback A1 is a code revert, but would reintroduce sensitive logging. B/C changes need client inventory, provider contracts, production-equivalent DB concurrency testing, and a reconciliation playbook before approval.
