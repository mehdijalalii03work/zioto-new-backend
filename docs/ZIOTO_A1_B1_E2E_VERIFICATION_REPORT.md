# A1/B1 checkout verification — 2026-11-12

## Verdict

**CONDITIONAL GO for controlled staging verification; NO-GO for a production release based on this evidence alone.** This was an isolated feature/unit test and source review, **not a browser end-to-end run**. No source code, dependencies, environment files, Git history, production data, or deployment were changed.

## Git state

| Repository | Branch / HEAD | Working tree |
| --- | --- | --- |
| Backend | `main` / `f84829a5ce9da2165b424e9637103e488c3106b5` | Tracked files clean; five pre-existing untracked payment reports preserved. |
| Frontend | `main` / `f114627e985b3c22981402042a7a601fb9dc53d9` | Clean. |

Backend `main` contains the reviewed A1 logging commit (`37a69ef`) followed by B1 authorization (`f84829a`). Frontend `main` contains B1 redirect recovery (`6253b15`) followed by the Nopay redirect fix (`f114627`). No unexpected tracked diff was found. The backend test worktree's tree hash equals backend `main` (`0eb0aedf147db5f66b00ef6e19c33bd705db5d96`). Local `origin/main` refs matched both HEADs when checked; no fetch was performed to verify current remote state.

## Test environment and results

- **Backend:** Tests ran in `/Users/mehdi/.codex/worktrees/a1-b1-backend-integration/zioto-new-backend`, which has no `.env`, `.env.testing`, or cached config. Herd Lite PHP 8.5.0 ran with a cleared process environment, SQLite `:memory:`, empty `DB_URL`, null queue, array cache/mail, and non-secret SMS/Shahkar placeholders. A macOS sandbox denied all network connections and file reads of the checkout's `.env.save*` and the original backend `.env*` files. These denials, plus the SQLite target, were checked before testing. No real gateway, OTP, SMS, banking, or Hesabfa request was made.
- **Backend tests:** 18 focused files covering payment initiation and callbacks, tenant auth/isolation, Nopay eligibility, order submission/stock/identity, gateway drivers, SMS service/jobs/observer, and sensitive logging. PHPUnit exited **0** with **579 assertions**, **34 clean passes and 125 warning-marked cases**, and **no test failures**. All 125 warning messages were the same missing-file warning for `.env` in the isolated worktree; no credentials were supplied from that file. The warning count means this is not a warning-free suite.
- **Frontend:** The existing Node 22 test runner executed both redirect files under a network-denied sandbox with `.env.local` unreadable: **12/12 passed**. TypeScript `tsc --noEmit --incremental false` passed under the same isolation. An initial attempt accidentally selected Node 20 from the sanitized `PATH` and could not import `.ts`; the explicit Node 22 rerun passed. No browser application, live API, or test user was started.

Backend test files: `PaymentSensitiveLoggingTest`, `PaymentControllerInitTest`, `PaymentControllerCallbackTest`, `PaymentInitiationAuthorizationTest`, `TenantAuthFlowTest`, `TenantIsolationTest`, `NopayProductEligibilityTest`, `DeferredIdentityOrderTest`, `OrderNotesTest`, `OrderStockValidationTest`, `OrderSubmitReuseTest`, `PaymentDriversTest`, `TenantOrderNumberingTest`, `CustomDigipayTest`, `SmsIrServiceTest`, `SendWelcomeSmsTest`, `OrderSmsNotificationObserverTest`, and `OrderSmsNotificationServiceTest`.

## Scenario coverage

| Requested scenario | Evidence obtained | Limit |
| --- | --- | --- |
| Main and Nopay OTP login/registration | Tenant auth feature tests passed with fake SMS; shared frontend `useAuthFlow` calls the same success callback after OTP or registration. | No browser OTP/registration journey. |
| Guest cart preservation after login | Frontend source keeps platform-specific local storage keys; redirect tests preserve safe checkout/cart destinations. | Cart contents were not checked in a browser. |
| Authenticated order creation and existing processing | Order submission, identity, stock and reuse feature tests passed. | No real storefront-to-API journey. |
| Valid payment initiation, foreign order and cross-platform rejection | `PaymentInitiationAuthorizationTest` exercised five mocked gateway selections, bearer failures, ownership, platform and no mutation/provider purchase on authorization failure. | No live provider purchase. |
| Duplicate callbacks and success/failure redirects | Callback feature tests cover duplicate paid callbacks, failed/successful state, bare token callbacks and redirect URLs. | No browser landing page confirmation. |
| Token-expiry recovery and unsafe `returnTo` | Main and Nopay redirect tests cover same-origin recovery, query preservation, external/scheme/protocol-relative and encoded attempts. | No browser session expiry. |
| Correct order/payment status display | Source review confirms authenticated `/api/payment/status/{orderId}` and frontend status usage. | No targeted status endpoint test or rendered status-display test was found. |

The frontend still calls `POST /api/payment/init` with `order_id` and `gateway` through its shared client, which adds a bearer token when present and `X-Platform`. The backend owner cases assert the existing `payment_url` and `transaction_id` response keys. `auth.token` applies to initiation; callback routes remain public. These are test/source findings, not a deployed-client compatibility guarantee.

## Gateway and authentication observations

Parsian, Digipay, Kamanlend, Smartis and Nopay were selected in mocked initiation tests. Existing driver tests exercised their local request/response handling; no gateway credentials, registered callback URL or provider service was verified. The Nopay live error `-99` (invalid merchant number) supplied earlier remains unresolved and was not reproduced here. Main and Nopay token/header isolation was covered by backend tests. Nopay's unsafe post-login external redirect is fixed in local frontend `main`; the browser flow was not exercised.

## Remaining risks and release blockers

1. No safe, already-configured browser E2E environment was found (no Playwright/Cypress project). Guest cart persistence, actual OTP/registration navigation, checkout recovery after 401, and rendered payment status remain unverified end to end.
2. The status endpoint has no focused test in the inspected suite. A controlled staging test should verify owner-only status and the displayed paid/failed states.
3. Provider calls were deliberately mocked. Staging must validate credentials, registered callbacks and response behavior for each gateway, especially Nopay's reported merchant-number rejection. Do not use a real financial transaction as a substitute for staging credentials.
4. The backend repository contains tracked `.env.save*` files whose contents were not inspected. Review their handling separately as a credential-exposure risk. The bearer token pasted earlier in this conversation should be revoked; its revocation was not verified.
5. PHPUnit's 125 missing-`.env` warnings are an isolation artifact but should be resolved in a dedicated test-safe setup before treating a warning-free suite as a release gate. The frontend's existing `next lint` script is incompatible with installed Next 16; lint was not a passing gate.

No commit, push, migration, production API call, or deployment was performed in this verification.
