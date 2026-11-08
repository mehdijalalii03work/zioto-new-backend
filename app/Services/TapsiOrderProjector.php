<?php

namespace App\Services;

use App\Models\User;
use App\Support\Platform;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Order\Models\Order;
use Modules\Product\Models\Product;

class TapsiOrderProjector
{
    public function apply(array $summary, array $details): Order
    {
        $tapsiOrderId = (string) ($summary['id'] ?? '');
        if ($tapsiOrderId === '' || ! isset($summary['finalPrice']) || ! is_numeric($summary['finalPrice'])) {
            throw new \InvalidArgumentException('Tapsi order ID or final price is missing.');
        }

        if (! isset($details['data']['items']) || ! is_array($details['data']['items'])) {
            throw new \InvalidArgumentException('Tapsi order items are missing.');
        }

        $statusId = (string) ($summary['stateCode'] ?? $summary['orderStatusId'] ?? '9');
        if (! in_array($statusId, ['4', '6', '9'], true)) {
            throw new \InvalidArgumentException('Unsupported Tapsi order status.');
        }

        return DB::transaction(function () use ($summary, $details, $tapsiOrderId, $statusId): Order {
            $order = Order::withoutTenantScope()->where('tapsi_order_id', $tapsiOrderId)->lockForUpdate()->first();
            $isNew = $order === null;
            $order ??= new Order;
            $order->platform = Platform::TAPSI;
            $order->tapsi_order_id = $tapsiOrderId;
            $order->tapsi_order_number = (string) ($summary['orderNumber'] ?? $tapsiOrderId);
            $order->order_number = $order->tapsi_order_number;
            $order->tapsi_order_status_id = $statusId;
            $order->status = match ($statusId) {
                '4' => 'confirmed', '9' => 'completed', '6' => 'cancelled',
            };
            $order->payment_status = 'paid';
            $order->total_amount = (int) round((float) $summary['finalPrice']);
            $order->tapsi_service_fee = (int) round((float) ($summary['serviceFee'] ?? 0));
            $order->tapsi_voucher_fee = (int) round((float) ($summary['voucherTotalFee'] ?? 0));
            $order->tapsi_shipment_bundle = $summary['shipmentOrderBundleNumbers'][0] ?? null;
            $order->tapsi_delivery_method = match ((string) ($summary['deliveryMethod'] ?? '')) {
                '1' => 'vendor', '2' => 'platform', '3' => 'pickup', default => $order->tapsi_delivery_method,
            };
            $order->tapsi_synced_at = now();
            if ($isNew) {
                $order->user_id = $this->findOrCreateUser($summary)?->id;
                if (! empty($summary['createdOn'])) {
                    $order->created_at = Carbon::parse($summary['createdOn']);
                }
            }
            $order->save();

            $seenItemIds = [];
            foreach ($details['data']['items'] ?? [] as $index => $item) {
                $itemId = (string) ($item['orderItemId'] ?? $tapsiOrderId.'-'.$index);
                $seenItemIds[] = $itemId;
                $sku = (string) ($item['sku'] ?? $item['productId'] ?? '');
                $product = $sku === '' ? null : Product::withoutTenantScope()
                    ->where('tapsi_product_id', $sku)->orWhere('sku', $sku)->first();
                $quantity = max(0, (int) ($item['quantity'] ?? 1));
                $price = (int) round((float) ($item['finalPrice'] ?? $item['price'] ?? 0));
                $orderItem = $order->items()->where('tapsi_order_item_id', $itemId)->first()
                    ?? $order->items()->whereNull('tapsi_order_item_id')->where('product_name', $item['name'] ?? $sku)->first()
                    ?? $order->items()->make();
                $orderItem->tapsi_order_item_id = $itemId;
                $orderItem->product_id = $product?->id;
                $orderItem->product_name = ($item['name'] ?? $product?->name ?? $sku) ?: 'Tapsi Item';
                $orderItem->product_price = (int) round((float) ($item['price'] ?? $price));
                $orderItem->quantity = $quantity;
                $orderItem->subtotal = $price;
                $orderItem->save();
            }
            $order->items()->whereNotIn('tapsi_order_item_id', $seenItemIds)->delete();

            $shipment = $details['data']['shipments'][0] ?? null;
            if ($shipment && ! $order->shipping()->exists()) {
                $order->shipping()->create([
                    'shipping_method_id' => 0,
                    'shipping_method_name' => 'تپسی شاپ',
                    'shipping_cost' => 0,
                    'tracking_number' => $shipment['number'] ?? null,
                ]);
            }

            return $order->refresh();
        });
    }

    private function findOrCreateUser(array $summary): ?User
    {
        $phone = preg_replace('/\D/', '', (string) ($summary['customerMobile'] ?? ''));
        if ($phone === '') {
            return null;
        }
        if (str_starts_with($phone, '98') && strlen($phone) === 12) {
            $phone = '0'.substr($phone, 2);
        }
        $existing = User::withoutTenantScope()->where('phone', $phone)->first();
        if ($existing) {
            return $existing;
        }

        $name = (string) ($summary['customerFullName'] ?? 'مشتری تپسی');
        $parts = explode(' ', trim($name));

        return User::withoutTenantScope()->create([
            'platform' => Platform::TAPSI,
            'name' => $name,
            'first_name' => $parts[0] ?? null,
            'last_name' => implode(' ', array_slice($parts, 1)) ?: null,
            'phone' => $phone,
            'national_code' => $summary['customerNationalCode'] ?? null,
            'password' => bcrypt(Str::random(32)),
        ]);
    }
}
