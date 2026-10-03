---
paths:
  - Modules/Product/app/Models/Product.php
---

# Models

## Compute sellable stock only via Product::sellableStockFor()
`hesabfa_reserved_stock` is written unconditionally by the order lifecycle (StockReservationObserver: +qty on entering `confirmed`, -qty on leaving it). Whether those writes *affect* availability is a separate, dynamic switch: the DB setting `ignore_reserved_stock` (default `true`, toggled at /admin/settings → Hesabfa → «تنظیمات رزرو موجودی»), read via `Product::ignoresReservedStock()`. Never re-inline `physical - reserved - manual` anywhere: call `Product::sellableStockFor($physical)` (or the `sellable_stock` accessor) so the switch keeps working everywhere. `hesabfa_manual_reserved` is an admin decision and always applies, even when reservations are ignored. Note `HESABFA_ENABLE_RESERVED_STOCK` (config/env) is a *different*, older gate: it disables the writes themselves.
