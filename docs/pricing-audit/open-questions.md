# Open questions and evidence needed

| Question | Current status / needed evidence | Suggested owner | Impact |
|---|---|---|---|
| What exactly are the provider price denominations and fineness definitions? | **UNKNOWN.** Obtain current provider docs and sanitized sample fixtures/contracts; do not infer from key labels alone. | Pricing / provider integration | Ensures Persian and Tala values are comparable before max/min. |
| What is each provider timestamp’s meaning and freshness SLA? | **UNKNOWN.** Current code ignores provider timestamps and records local fetch time. | Pricing operations | Defines stale quote policy and fallback safety. |
| Should two sources be required, and what disagreement threshold blocks selling? | **UNKNOWN.** Code accepts one and has no spread guard. | Business pricing owner + risk | Defines source quorum and anomaly handling. |
| Which authority may change live manual prices and coefficients? Is dual approval required? | **UNKNOWN.** Current edit permission is immediate; no approval flow found. | Product / finance / security | Controls single-admin error or compromise risk. |
| Which setting keys require audit, and how long must records be retained? | **UNKNOWN.** Observer allowlist is incomplete and sync bulk updates bypass it. | Security / compliance | Auditability and incident reconstruction. |
| What customer promise applies when displayed price changes before order submission? | **UNKNOWN.** Display and checkout recalculate; no quote token/expiry. | Product / legal / commerce | Required UX, quote lock, and payment policy. |
| Do user roles/time periods and product labor coefficients intentionally apply to all board-linked items? | **UNKNOWN.** Code applies them in product dynamic calculation; intent needs confirmation. | Commerce pricing owner | Product price correctness and parity. |
| Are `fee_off_hours` / `fee_business_hours` obsolete or used in another consumer? | Fields exist but this calculator uses `labor_coefficients`; inspect all consumers/data migrations. | Commerce engineering | Avoid hidden alternate price behavior. |
| What is the canonical physical fineness and instrument identity for Gold750, Gold995, Gold999.9, plus silver? | Business context says gold 995/999.9 bars and 750 plates; product records have independent `ayar` and board key. Verify catalog rows and taxonomy without exposing customer data. | Product/catalog owner | Prevents confusing physical purity with wallet identity. |
| Are rounding and amount scales consistent with gateway requirements? | **UNKNOWN.** Product calculations use float/BCMath mix; order total zero decimal; gateway amount passed through. | Payments / finance | Avoid under/overcharge and gateway unit mismatch. |
| Is `config('pricing.mode')=direct` active in deployed environments? | **UNKNOWN.** Source defaults to dynamic; deployed env not inspected. | Operations | Determines whether Tokeniko direct sync is operationally active. |
| Do product API and frontend consumers interpret all prices as Rial? | API labels and code indicate Rial, but frontend repository is absent from this checkout. | Frontend / commerce | Avoid currency display conversion errors. |
| Does any path besides `OrderSubmitController` create orders or order items with different price authority? | **UNKNOWN.** Legacy import, admin, Tapsi and integrations may create records. Audit all write paths before claiming global checkout guarantees. | Commerce engineering | Scope of server-side price validation. |

## First evidence to collect

1. Provider documentation and sanitized response contract examples, including units, timestamps, and fineness.
2. Production-safe configuration state for source toggles and `pricing.mode` (never share secrets).
3. Decision record for source quorum, manual override approval, stale policy, and customer price lock.
4. Inventory of every order-writing path and frontend price formatter.
5. Security review of Tala TLS and query-string token transport, followed by authorized remediation outside this audit.
