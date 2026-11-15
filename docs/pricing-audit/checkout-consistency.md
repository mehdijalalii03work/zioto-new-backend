# Display-to-payment consistency

## Lifecycle

| Stage | Price behavior | Evidence |
|---|---|---|
| Product API | Calls `priceFor()` on serialization, otherwise stored `products.price`; product response price is integer-cast Rial and includes unit price/pack size | `app/Http/Resources/ProductResource.php:23-37,46-67` |
| Cart API | Calls `priceFor()` when cart resource serializes; otherwise stored product price | `app/Http/Resources/CartResource.php:11-34` |
| Fee preview | Recomputes each requested product’s live price, then adds shipping, discount, installment fee | `app/Http/Controllers/Api/PaymentController.php:41-99` |
| Order submission | Recalculates each cart item, multiplies by quantity, then persists product price, subtotal, pricing breakdown and final order total | `app/Http/Controllers/Api/OrderSubmitController.php:447-532` |
| Payment initialization | Uses existing `orders.total_amount` in invoice and payment record | `app/Http/Controllers/Api/PaymentController.php:190-228` |

## What is frozen, what can change

The order item and order amount snapshot are persisted. `OrderItem` casts product price/subtotal to zero decimal and board/labor/final breakdown fields to decimal scales; the order total is decimal scale zero (`Modules/Order/app/Models/OrderItem.php:33-45`; `Modules/Order/app/Models/Order.php:91-98`). A later payment-init request charges that stored order total. It does not rerun the product formula.

An order can be recalculated when a similar pending order is reused: the controller recalculates order data and updates total/shipping on the existing order (`OrderSubmitController.php:534-628`). Payment initialization rejects already-paid orders and pending/processing payment duplicates, but no price expiry/requote check appears in the shown flow (`PaymentController.php:101-156`).

## Consistency conclusion

The product/cart display can diverge from checkout because each read can see a different board cache, time period, user role, setting, or Tokeniko response. Checkout recalculates again and snapshots; payment then uses that snapshot. There is no price quote identifier, signed quote, version, or display-to-order consistency token in the reviewed code. Therefore a guarantee that the customer pays the amount previously displayed is **not established**. Persisted order amount is the payment amount source after order creation, except a similar pending order may be refreshed before payment.

## Transactions and concurrency

Order creation is wrapped in a database transaction, with discount-code locking and up to three attempts (`OrderSubmitController.php:208-234`). It does not lock board cache, settings, or provider data. Scheduled sync writes cache first, then updates catalog prices in a separate DB transaction, and broadcasts after persistence (`SyncPriceBoard.php:41-82,154-179`). This leaves time-of-check/time-of-use windows between display, checkout calculation, order snapshot, and gateway initiation.

## Amounts and units

Dynamic calculator returns Rial (`DynamicPriceService.php:18-21,81-84`). API resources integer-cast prices, order creation uses numeric products and stores `total_amount` decimal scale zero, while payment invoice uses it directly (`OrderSubmitController.php:459-497,513-530`; `PaymentController.php:190-228`). Fee-preview and order arithmetic include PHP numeric types; exact rounding equivalence between preview and final order is not guaranteed by tests reviewed here.
