# Financial Core handoff

This handoff describes the current Commerce boundary and proposed integration questions. It does not design or implement the Financial Core.

## Verified Commerce concepts

- Internal board identities include `Gold750`, `Gold995`, `Gold999`, `Gold9999`, `Silver999`, and `Silver9999` with `_Sell`/`_Buy` keys. Product stores `price_board_item`, separate `metal_type`, `ayar`, and weight fields (`DynamicPriceService.php:23-36`; `Modules/Product/app/Models/Product.php:38-48,63-87`). These identifiers are not interchangeable by assumption.
- Gold 995 and 999.9 derive from Gold999 using business-defined multipliers. Silver 999 derives from manual Silver9999 value. This is not a universal fineness-ratio conversion (`ApiClientService.php:365-402`).
- Board and product/order monetary amounts are generally Rial; manual override UI says Toman/gram and converts by 10. Provider mapping assumes Tala `value` is Toman and Persian `price` is already in internal unit (`ApiClientService.php:280-287,380-394,728-745`). Provider contract semantics remain unverified.
- Product dynamic pricing adds weight-based base and labor coefficient, then taxes/rounding, and converts result back to Rial (`DynamicPriceService.php:72-84,287-327`).
- `orders.total_amount` and item price snapshots feed gateway payment; payment verifies the gateway transaction against the stored order amount (`PaymentController.php:190-228,398-414`).

## Commerce-to-Core contract proposal (PROPOSED)

Commerce should submit an intent/reference payload, not a trusted amount:

```json
{
  "commerce_order_id": "opaque stable ID",
  "items": [{
    "commerce_product_id": "opaque ID",
    "instrument_reference": "commerce catalog identity",
    "metal": "gold",
    "fineness_milli": 995,
    "gross_weight_mg": 1000000,
    "quantity": 1
  }],
  "requested_at": "RFC3339 timestamp",
  "commerce_price_snapshot": {
    "amount_minor": 123456,
    "currency": "IRR",
    "quote_reference": "optional, informational only"
  }
}
```

Every property and scale above is **PROPOSED**, not current API behavior. Prefer integer money minor units and integer weight units to remove ambiguous floats; Core must define exact currency exponent, fineness representation, weight scale, instrument mapping, and rounding rules. Core should return an authoritative quote/decision reference, payable amount, currency, expiry, policy/source metadata, and a tamper-evident signature or equivalent authenticated reference.

## Core must independently verify

- product/instrument identity and whether 750, 995, 999.9 bars/plates map to permitted wallet/OTC instruments;
- source values, source freshness, source disagreement, normalization, denomination, and fineness basis;
- all coefficients, spreads, fees, taxes, discounts and rounding rules, rather than trusting Commerce settings;
- amount × quantity, package semantics, current availability, quote expiry, duplicate order/replay behavior, and settlement amount;
- whether physical product labor premiums apply to any financial instrument (do not infer that from Commerce physical-product rules).

## Open integration decisions

The source units and exact upstream contracts need verification. Decide whether Commerce persists Core quote references and uses them as the only payable price; define cancellation/reprice/expiry behavior; define one authority for manual overrides and approval; and keep physical stock and instrument balance separate. See [open questions](open-questions.md).
