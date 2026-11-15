# Security and trust findings

Severity labels describe code-level risk, not evidence of exploitation.

## Findings ranked by severity

### High — VERIFIED DEFECT: Tala TLS certificate validation is disabled

`ApiClientService::fetchTalaApi()` calls `withoutVerifying()` before sending authenticated pricing data (`app/Services/Pricing/ApiClientService.php:644-650`). This removes normal certificate identity verification and can expose the token and permit response manipulation by an on-path party. The request also puts the token into a URL query string (`:640-643`), which can be captured in URL logs/telemetry. No exploitation is asserted.

### High — POTENTIAL RISK: external provider response controls board monetary values

Provider field values are parsed and trusted after limited structural/positive checks; disagreement is handled by unconditional max/min, with no outlier threshold or independent consistency check (`ApiClientService.php:245-311,313-363,698-745`). A compromised or semantically changed feed can move sale/purchase prices. Provider timestamps are ignored. No evidence of response signatures, source quorum, or bounds.

### High — MISSING CONTROL: no independent authoritative quote at checkout

Commerce computes display and checkout amounts, persists them, and then supplies order total to the payment gateway (`ProductResource.php:23-30`; `OrderSubmitController.php:447-532`; `PaymentController.php:190-228`). Future Financial Core must not accept Commerce monetary amounts as authoritative without independent price validation and quote policy.

### Medium — POTENTIAL RISK: display-to-order TOCTOU

Product/cart API computes on read; order submission recomputes later, so price can change between display and checkout. The order transaction does not lock board/settings and there is no quote ID/version (`ProductResource.php:28-30`; `CartResource.php:16-23`; `OrderSubmitController.php:457-480`). A clear customer confirmation/expiry policy is not evidenced.

### Medium — MISSING CONTROL: no approval step for pricing changes

Users with edit permission can save manual prices, coefficients and source switches directly; no second-person approval is visible (`ManualPricesPage.php:82-101`; `CoefficientsPage.php:143-180`; `PricingSettingsPage.php:96-127`). RBAC limits access, but policy sufficiency and segregation are business decisions.

### Medium — MISSING CONTROL: audit coverage is incomplete and automated writes bypass observer

Pricing settings observer uses an explicit allowlist; certain source/cache-related keys are omitted (`PricingSettingAuditObserver.php:46-65`). Product price update through query-builder in scheduled sync bypasses model observers (`SyncPriceBoard.php:173-177`). Thus audit log is not a complete record of all effective price changes. Audit schema includes actor/IP fields, but retention/integrity/alerting are not demonstrated (`2026_10_19_000000_create_price_audit_logs_table.php:11-23`).

### Medium — POTENTIAL RISK: fallback can preserve stale price

Provider raw values and computed board are reused under stale policy; stale blocking defaults false, and max age default is six hours (`ApiClientService.php:813-859`; `PricingSettings.php:136-143`; seeder lines 38-39). A stale board may still be offered unless operations enable blocking or other sales controls intervene.

### Medium — POTENTIAL RISK: public forced-refresh endpoint can trigger upstream traffic

`POST /api/price-board/refresh` is not in an auth middleware group in the route declaration and is protected only by `throttle:10,1` (`routes/api.php:90-91`). Its controller calls `PriceBoardService::refresh()`, which clears cache and forces external reads (`PriceBoardController.php:28-30`; `ApiClientService.php:158-169`). Repeated requests within the throttle limit can generate provider traffic and cache churn. Route middleware at bootstrap/deployment layers was not independently validated, so exposure is **POTENTIAL RISK**, not a confirmed unauthenticated exploit.

### Low — POTENTIAL RISK: numeric precision/rounding differs by path

Board values become floats, product formula accepts floats, and order persistence/API paths cast values to integer-scale amounts. Several rounding operations can occur at separate stages (`BcmathHelper.php:17-62,102-104`; `DynamicPriceService.php:287-327`; `OrderItem.php:33-45`). This can create small amount drift; business tolerance is **UNKNOWN**.

## Other trust boundaries

- **Manual overrides:** are direct database settings, not source-bound or time-limited. Their values override the gold sale base and can impact buying formulas; silver 999.9 manual input defines the sell base. No approval needed in code.
- **Cache poisoning/staleness:** no external cache access controls can be determined from repo source. `zioto:payload` is trusted on read if its shape matches; cache backend compromise is an operational threat, **UNKNOWN**.
- **Checkout tampering:** submitted order request is based on server cart rows, not a client-supplied product price in the inspected order data path. This is a protective design; tests/source should confirm every alternate order path before claiming universal resistance.
- **Concurrent update:** separate board cache and product persistence have no shared transaction; board may move mid-order calculation.
- **Replay:** no signed quote or quote age check is present. Payment callbacks do validate gateway transactions and use stored payment/order amounts; this does not establish freshness of original price.
- **Missing source:** one provider can suffice and invalid values are skipped, but there is no minimum-source quorum or disagreement safety lock.

## Financial Core trust boundary

Commerce may provide product IDs/SKUs, nominal product metal/purity/weight/pack size as descriptive references, cart quantity, order snapshot and a traceable commerce order ID. Financial Core should independently validate instrument identity, current quote/source authority, currency/units, coefficient policy, quote timestamp/expiry, arithmetic and payable amount. It must not treat client or Commerce-supplied price, discount, tax, or total as independently authoritative without a signed/verified pricing decision.
