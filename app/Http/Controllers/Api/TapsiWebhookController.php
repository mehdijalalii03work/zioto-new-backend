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
        $this->logWebhook($request);

        if (! config('tapsi.enabled')) {
            Log::warning('[TapsiWebhook] Tapsi integration is disabled');

            return response()->json(['error' => 'Tapsi integration disabled'], 503);
        }

        $token = $request->header('tapsishop-hub-webhook-authorization')
            ?? $request->header('tapsishopauthorization')
            ?? $request->header('tapsi-shop-hub-authorization');

        $expectedToken = config('tapsi.auth_token');
        if ($expectedToken && $token !== $expectedToken) {
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
        if ($changeType != 1) {
            Log::info('[TapsiWebhook] Ignoring non-create event', [
                'order_id' => $tapsiOrderId,
                'change_type' => $changeType,
            ]);

            return response()->json(['success' => true, 'message' => 'Event ignored']);
        }

        if (Order::withoutTenantScope()->where('tapsi_order_id', $tapsiOrderId)->exists()) {
            Log::info('[TapsiWebhook] Duplicate order ignored', ['order_id' => $tapsiOrderId]);

            return response()->json(['success' => true, 'message' => 'Duplicate']);
        }

        try {
            $order = DB::transaction(function () use ($tapsiOrderId, $orderDetail, $items) {
                return $this->createOrder($tapsiOrderId, $orderDetail, $items);
            });

            Log::info('[TapsiWebhook] Order created', [
                'order_id' => $order->id,
                'tapsi_order_id' => $tapsiOrderId,
            ]);

            return response()->json([
                'success' => true,
                'order_id' => $order->id,
            ]);
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
        $finalPrice = (float) ($items[0]['finalPrice'] ?? 0);
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
            'product_id' => $product?->id,
            'product_name' => $product?->name ?? $sku ?? 'Tapsi Item',
            'product_price' => (int) round($finalPrice / max($quantity, 1)),
            'quantity' => $quantity,
            'subtotal' => (int) round($finalPrice),
        ]);
    }

    private function logWebhook(Request $request): void
    {
        $logDir = storage_path('logs/tapsi-webhooks');
        if (! is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        $logFile = $logDir.'/webhook-'.date('Y-m-d').'.log';
        $entry = [
            'timestamp' => now()->toIso8601String(),
            'ip' => $request->ip(),
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'headers' => $request->headers->all(),
            'query' => $request->query(),
            'body' => $request->all(),
            'raw_body' => $request->getContent(),
        ];

        file_put_contents($logFile, json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)."\n---\n", FILE_APPEND);

        $this->cleanOldLogs($logDir);
    }

    private function cleanOldLogs(string $logDir): void
    {
        $files = glob($logDir.'/webhook-*.log');
        $cutoff = now()->subDays(30);

        foreach ($files as $file) {
            $dateStr = str_replace('webhook-', '', basename($file, '.log'));
            try {
                if (Carbon::parse($dateStr)->lt($cutoff)) {
                    unlink($file);
                }
            } catch (\Exception $e) {
                // skip
            }
        }
    }
}
