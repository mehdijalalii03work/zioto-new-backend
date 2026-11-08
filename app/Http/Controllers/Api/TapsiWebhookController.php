<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Platform;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Order\Models\Order;
use Modules\Product\Models\Product;

class TapsiWebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        if (! config('tapsi.enabled')) {
            Log::warning('[TapsiWebhook] Tapsi integration is disabled');

            return response()->json(['error' => 'Tapsi integration disabled'], 503);
        }

        $token = $request->header('TapsiShop.Hub.Webhook-Authorization')
            ?? $request->header('Tapsi-Shop-Hub-Authorization')
            ?? $request->header('tapsishopauthorization')
            ?? $request->header('tapsishop-hub-webhook-authorization');

        $expectedToken = config('tapsi.auth_token');
        if (! $expectedToken || ! is_string($token) || ! hash_equals($expectedToken, $token)) {
            Log::warning('[TapsiWebhook] Invalid token');

            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $data = $request->all();
        $orderDetail = $data['orderDetail'] ?? null;
        $items = $data['items'] ?? [];

        if (! $orderDetail) {
            return response()->json(['error' => 'No orderDetail'], 400);
        }

        $tapsiOrderId = (string) ($orderDetail['orderId'] ?? '');
        if (empty($tapsiOrderId)) {
            return response()->json(['error' => 'No orderId'], 400);
        }

        $changeType = $orderDetail['changeType'] ?? null;
        if (! in_array((int) $changeType, [1, 2], true)) {
            return response()->json(['succeed' => false, 'message' => 'Unsupported change type'], 422);
        }

        try {
            $result = DB::transaction(function () use ($tapsiOrderId, $orderDetail, $items, $changeType): string {
                $order = Order::withoutTenantScope()->where('tapsi_order_id', $tapsiOrderId)->lockForUpdate()->first();
                if ((int) $changeType === 2 && ! $order) {
                    throw new \DomainException('Order not found for cancellation');
                }
                if ((int) $changeType === 1 && ! $order) {
                    $order = $this->createOrder($tapsiOrderId, $orderDetail, $items);
                }
                if ((int) $changeType === 1 && $order->wasRecentlyCreated) {
                    foreach ($items as $item) {
                        $this->recordRequest($tapsiOrderId, $item);
                    }

                    return 'Created';
                }
                $changed = false;
                foreach ($items as $item) {
                    if (! $this->recordRequest($tapsiOrderId, $item)) {
                        continue;
                    }
                    if ((int) $changeType === 2) {
                        $orderItem = $order->items()->where('tapsi_order_item_id', (string) ($item['orderItemId'] ?? ''))->first();
                        if (! $orderItem) {
                            throw new \DomainException('Order item not found for cancellation');
                        }
                        $oldQuantity = (int) $orderItem->quantity;
                        $quantity = max(0, $oldQuantity - abs((int) ($item['quantity'] ?? 0)));
                        $orderItem->subtotal = $oldQuantity > 0 ? (int) round((float) $orderItem->subtotal * $quantity / $oldQuantity) : 0;
                        $orderItem->quantity = $quantity;
                        $orderItem->save();
                        $changed = true;
                    }
                }
                if ($changed) {
                    $order->total_amount = $order->items()->sum('subtotal');
                    $order->tapsi_synced_at = null;
                    if ($order->items()->sum('quantity') === 0) {
                        $order->status = 'cancelled';
                        $order->tapsi_order_status_id = '6';
                    }
                    $order->save();
                }

                return $changed ? 'Updated' : 'Duplicate';
            });

            return response()->json([
                'succeed' => true,
                'message' => $result,
                'success' => true,
            ]);
        } catch (\DomainException $e) {
            Log::warning('[TapsiWebhook] Event cannot be applied', ['order_id' => $tapsiOrderId, 'reason' => $e->getMessage()]);

            return response()->json(['succeed' => false, 'message' => $e->getMessage()], 409);
        } catch (\Throwable $e) {
            Log::error('[TapsiWebhook] Failed to create order', [
                'order_id' => $tapsiOrderId,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Internal error'], 500);
        }
    }

    public function test(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'message' => 'وب‌هوک تپسی شاپ فعال است',
        ]);
    }

    private function createOrder(string $tapsiOrderId, array $orderDetail, array $items): Order
    {
        $user = $this->findOrCreateUser($orderDetail);

        $orderNumber = (string) ($orderDetail['orderNumber'] ?? $tapsiOrderId);
        $finalPrice = array_sum(array_map(static fn (array $item): float => (float) ($item['finalPrice'] ?? 0), $items));
        $createdOn = $orderDetail['createdOnTimestamp'] ?? null;

        $order = new Order;
        $order->timestamps = false;
        $order->platform = Platform::TAPSI;
        $order->order_number = $orderNumber;
        $order->user_id = $user?->id;
        $order->status = 'pending';
        $order->payment_status = 'paid';
        $order->total_amount = (int) round($finalPrice);

        $order->tapsi_order_id = $tapsiOrderId;
        $order->tapsi_order_status_id = null;
        $order->tapsi_order_number = $orderNumber;

        $order->notes = json_encode(array_filter([
            'customer_name' => $orderDetail['customerFullName'] ?? null,
            'customer_first_name' => $orderDetail['customerFirstName'] ?? null,
            'customer_last_name' => $orderDetail['customerLastName'] ?? null,
            'customer_phone' => $orderDetail['customerMobile'] ?? null,
            'customer_national_code' => $orderDetail['customerNationalCode'] ?? null,
            'receiver_name' => $orderDetail['receiverFullName'] ?? null,
            'receiver_phone' => $orderDetail['receiverMobile'] ?? null,
            'delivery_address' => $orderDetail['deliveryAddress'] ?? null,
            'city' => $items[0]['cityName'] ?? $orderDetail['cityName'] ?? null,
            'province' => $items[0]['provinceName'] ?? $orderDetail['provinceName'] ?? null,
            'postal_code' => $items[0]['postalCode'] ?? $orderDetail['postalCode'] ?? null,
            'latitude' => $orderDetail['latitude'] ?? null,
            'longitude' => $orderDetail['longitude'] ?? null,
        ], fn ($v) => $v !== null && $v !== ''));

        $order->shipping_address_snapshot = json_encode(array_filter([
            'full_name' => $orderDetail['receiverFullName'] ?? $orderDetail['customerFullName'] ?? null,
            'phone' => $orderDetail['receiverMobile'] ?? $orderDetail['customerMobile'] ?? null,
            'address' => $orderDetail['deliveryAddress'] ?? null,
            'city' => $items[0]['cityName'] ?? $orderDetail['cityName'] ?? null,
            'province' => $items[0]['provinceName'] ?? $orderDetail['provinceName'] ?? null,
            'postal_code' => $items[0]['postalCode'] ?? $orderDetail['postalCode'] ?? null,
        ], fn ($v) => $v !== null && $v !== ''));

        if ($createdOn) {
            $order->created_at = Carbon::parse($createdOn);
            $order->updated_at = Carbon::parse($createdOn);
        }

        $order->save();

        foreach ($items as $item) {
            $this->createOrderItem($order, $item);
        }

        return $order;
    }

    private function findOrCreateUser(array $orderDetail): ?User
    {
        $phone = $orderDetail['customerMobile'] ?? null;
        if (empty($phone)) {
            return null;
        }

        $phone = ltrim($phone, '0');
        $phone = '0'.$phone;

        $user = User::withoutTenantScope()
            ->where('phone', $phone)
            ->where('platform', Platform::TAPSI)
            ->first();

        if ($user) {
            return $user;
        }

        $firstName = $orderDetail['customerFirstName'] ?? '';
        $lastName = $orderDetail['customerLastName'] ?? '';
        $fullName = trim($firstName.' '.$lastName) ?: ($orderDetail['customerFullName'] ?? $phone);

        return User::withoutTenantScope()->create([
            'name' => $fullName,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'phone' => $phone,
            'national_code' => $orderDetail['customerNationalCode'] ?? null,
            'password' => bcrypt('tapsi_'.$phone),
            'platform' => Platform::TAPSI,
        ]);
    }

    private function createOrderItem(Order $order, array $item): void
    {
        $sku = $item['productId'] ?? null;
        $product = $sku ? Product::where('sku', $sku)->first() : null;

        $finalPrice = (float) ($item['finalPrice'] ?? 0);
        $quantity = abs((int) ($item['quantity'] ?? 1));

        $order->items()->create([
            'tapsi_order_item_id' => isset($item['orderItemId']) ? (string) $item['orderItemId'] : null,
            'product_id' => $product?->id,
            'product_name' => $product?->name ?? $sku ?? 'Tapsi Item',
            'product_price' => (int) round($finalPrice / max($quantity, 1)),
            'quantity' => $quantity,
            'subtotal' => (int) round($finalPrice),
        ]);
    }

    private function recordRequest(string $tapsiOrderId, array $item): bool
    {
        if (! isset($item['requestId'])) {
            return true;
        }

        return DB::table('tapsi_webhook_requests')->insertOrIgnore([
            'request_id' => (string) $item['requestId'],
            'tapsi_order_id' => $tapsiOrderId,
            'created_at' => now(),
            'updated_at' => now(),
        ]) === 1;
    }
}
