# Zioto payment hardening report

## Executive summary

The payment lifecycle and its side effects were mapped in `docs/ZIOTO_PAYMENT_HARDENING_PLAN.md`. One Category A change was implemented: sensitive values were removed from payment log contexts. Public payment initiation, callback races, late payments, inconsistent pricing snapshots, stock reservation failure, and verified-payment persistence failure remain open because correcting them changes client or financial behavior. No database schema, payment request, route, API response, configuration, schedule, queue, authentication rule, or provider verification logic was changed.

This is source and isolated-test evidence, not a production safety guarantee. The pre-existing `docs/ZIOTO_FINANCIAL_CORE_DISCOVERY.md` is untracked user work and was not edited.

## Implemented change

**Change ID:** A1 — payment log minimization
**Finding:** Raw callback query/body, phone and national ID, Kamanlend terminal secret and response bodies, Smartis response body, Nopay token/redirect URL/response body, and receipt details were logged by `PaymentController` and gateway drivers. The Kamanlend purchase request log directly contained `terminalSecret`.
**Files changed:** `app/Http/Controllers/Api/PaymentController.php`, `app/Payment/Drivers/Kamanlend/Kamanlend.php`, `app/Payment/Drivers/Smartis/Smartis.php`, `app/Payment/Drivers/Nopay/Nopay.php`, `tests/Feature/Api/PaymentSensitiveLoggingTest.php`.
**Previous behavior:** These values appeared in the payment log context.
**New behavior:** Those contexts retain non-secret order/payment correlation, gateway, method, amount, endpoint/path and HTTP status where available. Provider payloads, token parsing, stored receipt details, and redirect behavior are unchanged.
**Compatibility impact:** No public API or provider protocol change. Operators lose raw payloads and tokens from these particular log messages; attempt records and references remain available for correlation.
**Regression tests:** New tests cover callback and bare callback token redaction and Kamanlend purchase log redaction with a mocked provider response. Existing init, callback and driver tests cover the shared flows.
**Test result:** New test 3 passed / 18 assertions. Existing callback 13 passed / 51 assertions; init 8 passed / 19 assertions; driver 30 passed / 94 assertions.
**Residual risk:** Other payment error messages, provider response fields, receipt storage, Hesabfa logs and historical log files may still contain sensitive data. Log retention and access controls require operational review. The new tests do not exhaustively inspect every gateway log branch.

## Intentionally not implemented and approvals needed

| ID | Reason and approval gate |
| --- | --- |
| B1 — initiation ownership | `POST /api/payment/init` remains public and can create an attempt for a supplied order ID. Existing tests and potentially storefront/Nopay clients use anonymous initiation. Authenticate/authorize only after client inventory and migration plan; this is the highest-priority pre-wallet blocker. |
| B2 — atomic finalization | Processing lock ends before provider verify; final paid update has no locked order terminal check. Requires cross-gateway replay rules, real concurrency tests and observer side-effect policy. |
| B3 — cancellation conflict | Scheduler candidate selection can race callbacks; a late verified callback can confirm a cancelled order. Paid-after-cancel outcome needs stock, discount, refund and customer communication decisions. |
| B4 — quote consistency | Reused pending order total/shipping may change without item snapshot updates; an existing attempt may retain its old amount. Pricing/rounding and client display policy must be approved before correction. |
| B5 — inventory | Failed conditional reservation logs and continues with confirmed order. Stopping confirmation after external payment could strand captured funds; fulfillment/refund policy and stock authority must be decided. |
| C1 — reconciliation | Provider success followed by failed local finalization may leave no durable recovery record. Automatic recovery needs provider re-verification contracts, durable workflow and likely schema approval. |

## Test and review record

Tests ran under `phpunit.xml` with SQLite `:memory:`, array cache, and mocked gateway responses. No external payment request or production database connection was made by these tests. The first run of the new test failed on an assertion's route-ID type (string versus integer); the assertion was corrected and the singular test rerun successfully. `vendor/bin/pint --dirty --format agent` passed. `git diff --check` passed. The final diff was reviewed for unrelated changes. No full test suite, deployment-equivalent database concurrency test, provider sandbox test, or production configuration inspection was run.

Gateway consideration: all five providers share callback handling. Parsian status handling, Digipay tokenless fallback, Smartis amount check, Kamanlend terminal payload, and Nopay exact callback URL were left intact. The existing driver and callback tests pass, but their coverage does not prove provider contract compatibility in a deployed merchant account.

## Rollout and rollback

Deploy A1 as a code-only change. Review payment logs in a non-production environment for sufficient order/payment IDs and status codes, then review historical logs for credential exposure and rotate any exposed merchant credential under the team's incident process. Do not restore raw provider payload logging as a routine rollback. If operational troubleshooting needs more context, add specific allowlisted, non-secret fields after review. Monitor failed purchase/verification counts and manual-review alerts during rollout. Before Wallet integration, close B1 and establish approved resolution and reconciliation for B2/B3/C1, then reconcile pricing and stock policy for B4/B5.

## Final diff summary

Four PHP runtime files have log-context-only edits; one focused PHPUnit feature test and the requested plan/report were added. The untracked discovery report remains untouched.
