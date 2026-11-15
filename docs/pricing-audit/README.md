# ZIOTO commerce pricing audit

**Scope:** static, read-only review of the repository implementation. No live provider requests were made. Application code, configuration, database, cache, queue, and tests were not changed. This folder is the only authorized write scope.

## Executive overview

Commerce has a server-side price board built by `ApiClientService`, a per-product price calculator in `DynamicPriceService`, product and cart API resources that can recalculate prices on read, and order creation that calculates and persists an order amount and item snapshots. A minute scheduler refreshes the board and persists a basic-role product price. Payment initialization later charges the persisted `orders.total_amount`.

The business description is only partly accurate. PersianAPI and Tala.ir contribute to gold board prices, with `max` used as the gold sale base and `min` as the ordinary purchase base. They are not both required. Manual gold inputs override API-derived selling bases. Silver 999.9 is manual-only. An optional Tokeniko board can bypass the computed board, and optional Tokeniko product prices can bypass dynamic product calculation. The actual products’ fineness does not automatically select a fineness: each dynamic product points at a board key.

## Findings summary

- Gold pricing has independent rules for 750 and 999; the 995 and 999.9 board entries derive from 999.
- A source can stand alone. For equal source values, the purchase path uses the selling result multiplied by `coef_buy_price`; otherwise it uses the lower value times the relevant buy ratio.
- The dashboard’s “manual always wins” description is true for gold sell bases, but silver has no provider-derived sell price in this algorithm.
- The optional Tokeniko board mode takes raw Tokeniko buy/sell fields and bypasses the manual and fineness coefficient path.
- Product display and cart serialization compute a live price; order creation computes again and snapshots. Payment uses the stored order total. No evidence establishes that displayed price is guaranteed to match the order price.
- Tala transport disables TLS certificate verification and sends its token in a URL query parameter: **VERIFIED DEFECT** in transport protection.
- Price settings and product price fields have audit observers, but no approval workflow is evident. Some settings are not included in the audit observer’s allowlist.

## Navigation

- [Architecture](architecture.md)
- [Providers](providers.md)
- [Formulas and numeric behavior](formulas.md)
- [Admin settings and precedence](admin-settings.md)
- [Checkout consistency](checkout-consistency.md)
- [Security findings](security-findings.md)
- [Financial Core handoff](financial-core-handoff.md)
- [Open questions](open-questions.md)
- [Persian executive summary](summary.fa.md)

## Evidence labels and limits

- **VERIFIED IN CODE:** directly present in repository source/config/schema.
- **VERIFIED BY TEST:** asserted by a named repository test. This audit did not execute tests.
- **DOCUMENTED BUT NOT VERIFIED:** code comments or labels state behavior, without independent runtime evidence.
- **BUSINESS-PROVIDED CONTEXT:** supplied in the audit request, not treated as evidence.
- **INFERRED:** reasonable consequence of code, not an explicit product guarantee.
- **UNKNOWN:** repository evidence does not settle the question.

References use repository-relative paths and line numbers from the inspected worktree. Line numbers can drift as code changes. Existing worktree modifications were present during this audit; inspect `git status` before attributing any such changes to this documentation work.
