# B1 payment initiation authorization — implementation report

## Scope and Git state

Changes are confined to the existing `security/b1-payment-authorization` worktrees. The frontend and backend changes are committed separately. No push, merge, deployment, migration, production configuration change, or real provider request was made.

| Repository | Worktree | B1 base HEAD |
| --- | --- | --- |
| Backend | `/Users/mehdi/.codex/worktrees/b1-payment-authorization/zioto-new-backend` | `352b17fe9f9b90c609e1ca404befe165681a91b6` |
| Frontend | `/Users/mehdi/.codex/worktrees/b1-payment-authorization/zioto-new-frontend` | `9ff55ef1d8972ca5007bd72dc11b7acee7142034` |

The original backend checkout remains on `security/payment-hardening` with its five pre-existing untracked reports. The original frontend checkout remains clean on `main`. Neither original checkout was modified.

Frontend B1 commit: `f1a946f98366599646c2a6fcb2a46dd0d0c5ed4e` (`fix(auth): restore safe checkout redirect after login`). The backend B1 commit is the commit containing this report; inspect `git log -1` in the backend worktree for its SHA.

## Files changed and behavior

### Backend

- `routes/api.php`: add the existing `auth.token` middleware only to `POST /api/payment/init`. Both GET/POST callback routes remain public.
- `app/Http/Requests/InitPaymentRequest.php`: validate that `order_id` exists for the API-authenticated user's ID **and** platform. Foreign, cross-platform, nonexistent and ownerless orders receive the existing `422` order validation shape.
- `app/Http/Controllers/Api/PaymentController.php`: independently scope the init order lookup by authenticated user ID and platform before any previous attempt is failed, payment row is created, or gateway purchase is invoked.
- `tests/Feature/Api/PaymentInitiationAuthorizationTest.php` (new): owner and platform authorization, all five gateway selections, exact successful response, provider payload/callback URL, token failures, no unauthorized payment mutation/provider call, and owner retry behavior.
- `tests/Feature/Api/PaymentControllerInitTest.php`: authenticate existing owner cases and fake provider purchase in retry tests so they assert actual successful initiation.
- `tests/Feature/Api/NopayProductEligibilityTest.php`: authenticate the existing Nopay eligibility rejection case.

Before B1, a caller with an order ID could initiate payment anonymously. After B1, missing/invalid/expired tokens get the existing middleware `401` codes; a valid token must match the order's owner and platform. Authorized requests retain the `order_id`/`gateway` body, `payment_url`/`transaction_id` success JSON, payment state behavior, pricing, and gateway-specific callback URL construction. Gateway callbacks have no new bearer requirement.

### Frontend

- `src/app/pages/AuthPage.tsx`: resolve the post-OTP destination from the existing `redirect` parameter first, then `returnTo` used by token-expiry recovery.
- `src/lib/auth-redirect.ts` (new): accept only same-origin HTTP(S) URLs or internal paths, preserve path, query and fragment, and reject external or malformed destinations.
- `src/lib/auth-redirect.test.mjs` (new): cover redirect priority, checkout recovery, unsafe destinations, fallback and default.

OTP verification and Nopay authentication are unchanged. Login navigation does not create an order automatically; any checkout action remains user-driven.

## Verification

Backend dependencies used the locked versions under Herd Lite PHP 8.5.0 with MongoDB loaded. Only the required Composer merge plugin was enabled for autoload generation; application scripts were not run. Frontend dependencies used `yarn install --frozen-lockfile --ignore-scripts`; neither manifest nor lockfile was changed.

Backend tests used SQLite `:memory:` with process-local testing variables, placeholder SMS/Shahkar keys, null queue, and a macOS sandbox denying all network access. Before B1, the focused baseline passed **88 tests, 329 assertions**. After B1, the eight focused payment/tenant files passed **104 tests, 411 assertions**. The new authorization file alone passed **16 tests, 82 assertions**. No external provider, SMS service, or production database was contacted. `php artisan route:list --path=payment -v` shows `AuthenticateApiToken` on init and no auth middleware on either callback route. `git diff --check` passed.

Frontend `node --test src/lib/auth-redirect.test.mjs` passed **5/5**; `tsc --noEmit --incremental false` and `git diff --check` passed. These tests cover redirect resolution, not an end-to-end OTP/browser session.

PHP 8.5 produced no runtime error in the focused suite. This does not establish compatibility for the entire application or production PHP runtime.

## Gateway compatibility

The five mocked owner cases cover Parsian, Digipay, Kamanlend, Smartis and Nopay. They assert the existing `via()` selection, invoice amount and details, redirect URL, callback URL form, payment row and success JSON. Nopay uses its configured bare callback and Nopay return origin; the other selections use the order/gateway callback. The tests do not prove live provider behavior, credential validity, registered callback URLs, or deployed storefront revisions.

## Remaining risks and deployment prerequisites

**CONDITIONAL GO for a coordinated, reviewed rollout; no deployment performed.** B1 can be implemented in source and verified locally, but production rollout requires checking the deployed main and Nopay checkout revisions and any legacy, mobile or Tapsi callers. Anonymous or incorrectly headed init callers will now fail; there is no anonymous bypass. Confirm that each legitimate caller sends the platform-matched bearer token after order creation. Validate the deployed frontend's token-expiry return path and avoid automatic order recreation. Verify provider callback registrations and perform a staging smoke test with isolated credentials for each gateway. Have monitoring for init `401`/`422` rates and payment conversion during rollout.

The frontend repository's npm lockfile was already stale relative to its manifest; this work did not regenerate it. Builds should use the repository's established Yarn lockfile procedure until dependency metadata is reconciled separately. SQLite and mocked providers cannot reproduce every production database or provider behavior. A full suite and browser checkout test were outside this focused verification. PHP 8.5 deprecations elsewhere remain unassessed.

## Diff and rollback

The backend B1 commit contains six application/test files listed above plus this report; the frontend B1 commit contains three files, including two new files. No dependency, schema, environment or other payment area changed. Review each B1 commit against its base HEAD above.

For a pre-deployment rollback, leave both B1 commits unmerged and use the recorded base HEADs for the earlier state. If B1 has been merged, create reviewed revert commits for the frontend and backend B1 commits; preserve the original checkouts and their existing untracked reports. For a post-deployment rollback, restore the previously deployed backend and frontend releases together through the normal release process; verify that init and callback routes, checkout recovery and payment status return to the prior behavior. Do not reset, clean, or delete either worktree broadly.
