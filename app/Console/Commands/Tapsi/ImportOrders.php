<?php

namespace App\Console\Commands\Tapsi;

use App\Models\User;
use App\Services\TapsiShopService;
use App\Support\Platform;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\Order\Models\Order;
use Modules\Product\Models\Product;

class ImportOrders extends Command
{
    protected $signature = 'tapsi:import-orders
        {--from= : Start date (Y-m-d)}
        {--to= : End date (Y-m-d)}
        {--dry-run : Show stats without persisting}
        {--page-size=20 : Page size for Tapsi API}
        {--limit= : Max number of orders to import}
        {--debug : Show debug output}';

    protected $description = 'Import historical successful orders from Tapsi Shop';

    private int $imported = 0;

    private int $skipped = 0;

    private int $failed = 0;

    private int $limit = 0;

    private bool $dryRun = false;

    private bool $verbose = false;

    public function handle(TapsiShopService $tapsi): int
    {
        if (! config('tapsi.enabled')) {
            $this->error('Tapsi Shop sync is not enabled. Set TAPSI_SYNC_ENABLED=true.');

            return Command::FAILURE;
        }

        $lock = Cache::lock('tapsi:import-orders', 3600);

        if (! $lock->get()) {
            $this->error('Another import is already running. Try again later.');

            return Command::FAILURE;
        }

        try {
            $this->dryRun = (bool) $this->option('dry-run');
            $this->verbose = (bool) $this->option('debug');
            $this->limit = (int) ($this->option('limit') ?: PHP_INT_MAX);
            $pageSize = (int) $this->option('page-size');

            $fromDate = $this->option('from')
                ? Carbon::parse($this->option('from'))->startOfDay()->toIso8601String()
                : now()->subYear()->toIso8601String();

            $toDate = $this->option('to')
                ? Carbon::parse($this->option('to'))->endOfDay()->toIso8601String()
                : now()->toIso8601String();

            $this->newLine();
            $this->info('Tapsi Shop Order Import');
            $this->line("  From: {$fromDate}");
            $this->line("  To:   {$toDate}");
            $this->line('  Mode: '.($this->dryRun ? 'DRY RUN' : 'LIVE'));
            $this->newLine();

            $this->importOrders($tapsi, $fromDate, $toDate, $pageSize);

            $this->newLine();
            $this->info("Done! Imported: {$this->imported}, Skipped: {$this->skipped}, Failed: {$this->failed}");

            return Command::SUCCESS;

        } finally {
            $lock->release();
        }
    }

    private function importOrders(TapsiShopService $tapsi, string $fromDate, string $toDate, int $pageSize): void
    {
        if ($this->verbose) {
            $this->line('  Fetching order list from Tapsi API...');
        }

        $found = false;

        foreach ($tapsi->getAllSuccessfulOrders($fromDate, $toDate, $pageSize) as $orderData) {
            $found = true;

            if ($this->imported + $this->skipped + $this->failed >= $this->limit) {
                break;
            }

            $tapsiOrderId = (string) ($orderData['id'] ?? '');

            if (empty($tapsiOrderId)) {
                $this->warn('  Skipping order with empty ID');

                $this->failed++;

                continue;
            }

            try {
                $this->processOrder($tapsi, $tapsiOrderId, $orderData);
            } catch (\Throwable $e) {
                $this->failed++;
                $this->error("  Failed: {$tapsiOrderId} — {$e->getMessage()}");
                Log::error('[TapsiImport] Failed to process order', [
                    'tapsi_order_id' => $tapsiOrderId,
                    'error' => $e->getMessage(),
                ]);
            }

            // Tapsi API rate-limits order details to 1 request per 10 seconds
            $delay = (int) config('tapsi.order_detail_delay', 11);
            if ($delay > 0) {
                sleep($delay);
            }
        }

        if (! $found) {
            $this->warn('  No orders found from Tapsi API for the given date range.');
            $this->warn('  Check that:');
            $this->warn('    - TAPSI_AUTH_TOKEN is valid in .env');
            $this->warn('    - Date range contains completed orders');
            $this->warn('    - Run with --verbose for more details');
        }
    }

    private function processOrder(TapsiShopService $tapsi, string $tapsiOrderId, array $summaryData): void
    {
        if (Order::withoutTenantScope()->where('tapsi_order_id', $tapsiOrderId)->exists()) {
            $this->skipped++;
            $this->line("  Skipped (duplicate): {$tapsiOrderId}");

            return;
        }

        $details = $tapsi->getOrderDetails($tapsiOrderId);

        if (! ($details['success'] ?? false)) {
            $this->failed++;
            $this->warn("  Failed to fetch details: {$tapsiOrderId}");

            return;
        }

        $orderInfo = $details['data']['order'] ?? [];
        $items = $details['data']['items'] ?? [];
        $shipments = $details['data']['shipments'] ?? [];

        if ($this->option('debug')) {
            $this->line('  [DEBUG] Summary data keys: '.implode(', ', array_keys($summaryData)));
            $this->line('  [DEBUG] Order info keys: '.implode(', ', array_keys($orderInfo)));
            $this->line('  [DEBUG] Items count: '.count($items));
            $this->line('  [DEBUG] customerFullName: '.($summaryData['customerFullName'] ?? 'NULL'));
            $this->line('  [DEBUG] customerMobile: '.($summaryData['customerMobile'] ?? 'NULL'));
            $this->line('  [DEBUG] receiverFullName: '.($summaryData['receiverFullName'] ?? 'NULL'));
            if (isset($items[0])) {
                $this->line('  [DEBUG] Item 0 keys: '.implode(', ', array_keys($items[0])));
                $this->line('  [DEBUG] Item 0 customerMobile: '.($items[0]['customerMobile'] ?? 'NULL'));
            }
            if (isset($orderInfo)) {
                $this->line('  [DEBUG] Order info full: '.json_encode($orderInfo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            }
        }

        $customerMobile = $summaryData['customerMobile'] ?? $items[0]['customerMobile'] ?? null;
        $user = $this->findOrCreateUser($customerMobile, $summaryData);

        if ($this->dryRun) {
            $this->imported++;
            $this->line("  Dry run: {$tapsiOrderId} — ".count($items).' items');

            return;
        }

        DB::transaction(function () use ($tapsiOrderId, $summaryData, $orderInfo, $items, $shipments, $user) {
            $this->createOrder($tapsiOrderId, $summaryData, $orderInfo, $items, $shipments, $user);
        });

        $this->imported++;
        $this->line("  Imported: {$tapsiOrderId} — ".count($items).' items');
    }

    private function createOrder(string $tapsiOrderId, array $summaryData, array $orderInfo, array $items, array $shipments, ?User $user): void
    {
        $finalPrice = (float) ($summaryData['finalPrice'] ?? 0);
        $serviceFee = (float) ($summaryData['serviceFee'] ?? 0);
        $voucherFee = (float) ($summaryData['voucherTotalFee'] ?? 0);

        $order = new Order;
        $order->timestamps = false;
        $order->platform = Platform::TAPSI;
        $order->order_number = (string) ($summaryData['orderNumber'] ?? $tapsiOrderId);
        $order->user_id = $user?->id;
        $order->status = 'completed';
        $order->payment_status = 'paid';
        $order->total_amount = (int) round($finalPrice);

        $order->tapsi_order_id = $tapsiOrderId;
        $order->tapsi_order_number = $summaryData['orderNumber'] ?? null;
        $order->tapsi_shipment_bundle = $summaryData['shipmentOrderBundleNumbers'][0] ?? null;
        $order->tapsi_delivery_method = $this->mapDeliveryMethod($summaryData['deliveryMethod'] ?? null);
        $order->tapsi_service_fee = (int) round($serviceFee);
        $order->tapsi_voucher_fee = (int) round($voucherFee);

        $order->notes = json_encode(array_filter([
            'customer_name' => $summaryData['customerFullName'] ?? null,
            'customer_phone' => $summaryData['customerMobile'] ?? null,
            'customer_national_code' => $summaryData['customerNationalCode'] ?? null,
            'receiver_name' => $summaryData['receiverFullName'] ?? null,
            'receiver_phone' => $summaryData['receiverMobile'] ?? null,
            'delivery_address' => $summaryData['deliveryAddress'] ?? null,
            'order_date_shamsi' => $summaryData['persianDateTime'] ?? null,
            'tapsi_status' => $summaryData['stateTitle'] ?? null,
        ], fn ($v) => $v !== null && $v !== ''));

        $order->shipping_address_snapshot = json_encode(array_filter([
            'full_name' => $summaryData['receiverFullName'] ?? null,
            'phone' => $summaryData['receiverMobile'] ?? null,
            'address' => $summaryData['deliveryAddress'] ?? null,
        ], fn ($v) => $v !== null && $v !== ''));

        $createdOn = $summaryData['createdOn'] ?? null;
        if ($createdOn) {
            $order->created_at = Carbon::parse($createdOn);
            $order->updated_at = Carbon::parse($createdOn);
        }

        $order->save();

        $this->createOrderItems($order, $items);
        $this->createOrderShipping($order, $shipments);
    }

    private function createOrderItems(Order $order, array $items): void
    {
        foreach ($items as $item) {
            $finalPrice = (float) ($item['finalPrice'] ?? 0);
            $price = (float) ($item['price'] ?? $finalPrice);
            $quantity = (int) ($item['quantity'] ?? 1);

            $sku = $item['sku'] ?? $item['productId'] ?? null;
            $product = $this->findLocalProduct($sku);

            $order->items()->create([
                'product_id' => $product?->id,
                'product_name' => $item['name'] ?? $sku ?? "Tapsi Item #{$item['orderItemId']}",
                'product_price' => (int) round($price),
                'quantity' => $quantity,
                'subtotal' => (int) round($finalPrice),
            ]);
        }
    }

    private function createOrderShipping(Order $order, array $shipments): void
    {
        $shipment = $shipments[0] ?? null;

        if (! $shipment) {
            return;
        }

        $shippingMethodName = match ($order->tapsi_delivery_method) {
            'vendor' => 'ارسال فروشنده',
            'platform' => 'ارسال پلتفرم',
            'pickup' => 'تحویل حضوری',
            default => 'تپسی شاپ',
        };

        $order->shipping()->create([
            'shipping_method_id' => 0,
            'shipping_method_name' => $shippingMethodName,
            'shipping_cost' => 0,
            'tracking_number' => $shipment['number'] ?? null,
        ]);
    }

    private function findLocalProduct(?string $sku): ?Product
    {
        if (empty($sku)) {
            return null;
        }

        return Product::withoutTenantScope()
            ->where('tapsi_product_id', $sku)
            ->orWhere('sku', $sku)
            ->first();
    }

    private function findOrCreateUser(?string $phone, array $summaryData): ?User
    {
        if (empty($phone)) {
            return null;
        }

        $phone = preg_replace('/[^0-9]/', '', $phone);

        if (strlen($phone) === 13 && str_starts_with($phone, '98')) {
            $phone = '0'.substr($phone, 2);
        }

        if (strlen($phone) === 12 && str_starts_with($phone, '+98')) {
            $phone = '0'.substr($phone, 3);
        }

        $existing = User::withoutTenantScope()
            ->where('platform', Platform::TAPSI)
            ->where('phone', $phone)
            ->first();

        if ($existing) {
            return $existing;
        }

        $existingOnOtherPlatform = User::withoutTenantScope()
            ->where('phone', $phone)
            ->first();

        if ($existingOnOtherPlatform) {
            return $existingOnOtherPlatform;
        }

        $fullName = $summaryData['customerFullName'] ?? '';
        $parts = explode(' ', trim($fullName));

        return User::withoutTenantScope()->create([
            'platform' => Platform::TAPSI,
            'name' => $fullName ?: 'مشتری تپسی',
            'first_name' => $parts[0] ?? null,
            'last_name' => implode(' ', array_slice($parts, 1)) ?: null,
            'phone' => $phone,
            'national_code' => $summaryData['customerNationalCode'] ?? null,
            'password' => bcrypt(Str::random(32)),
        ]);
    }

    private function mapDeliveryMethod(?string $method): ?string
    {
        return match ((string) $method) {
            '1' => 'vendor',
            '2' => 'platform',
            '3' => 'pickup',
            default => null,
        };
    }
}
