<?php

namespace App\Console\Commands\WpMigration;

use App\Models\User;
use App\Services\WpMigration\WpDatabase;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\Order\Models\Order;

class ImportOrders extends Command
{
    protected $signature = 'migrate:wp-orders
                            {--dry-run : Only show stats without inserting}';

    protected $description = 'Import WordPress orders into Laravel';

    protected array $statusMap = [
        'wc-completed' => 'confirmed',
        'wc-processing' => 'confirmed',
        'wc-packing' => 'processing',
        'wc-delivered' => 'delivered',
        'wc-cancelled' => 'cancelled',
        'wc-failed' => 'cancelled',
        'wc-pending' => 'pending',
        'wc-on-hold' => 'pending',
        'wc-refunded' => 'cancelled',
        'auto-draft' => 'pending',
    ];

    protected array $paymentMethodMap = [
        'WC_Pec_Gateway' => 'online',
        'WCDigiPay' => 'installment',
        'kamanlend' => 'installment',
        'wc_smartis_gateway' => 'installment',
    ];

    protected array $gatewayMap = [
        'WC_Pec_Gateway' => 'pec',
        'WCDigiPay' => 'digipay',
        'kamanlend' => 'kamanlend',
        'wc_smartis_gateway' => 'smartis',
    ];

    public function handle(WpDatabase $wp): int
    {
        if (! $wp->table('user_mapping')->exists()) {
            $this->error('User mapping table not found. Run migrate:wp-users first.');

            return Command::FAILURE;
        }

        if (! $wp->table('product_sku_map')->exists()) {
            $this->warn('Product SKU map not found. Run migrate:wp-products-map first.');
        }

        $wp->table('wc_orders')->exists();
        $totalOrders = $wp->table('wc_orders')->count();
        $existingOrders = Order::withoutTenantScope()->count();
        $lastOrderNumber = (int) DB::table('orders')->max(DB::raw('CAST(order_number AS UNSIGNED)')) ?? 21000;

        $this->line("WordPress orders: $totalOrders");
        $this->line("Existing Laravel orders: $existingOrders");
        $this->line('Next order number will start from: '.($lastOrderNumber + 1));

        $this->ensureMappingTable($wp);

        $imported = 0;
        $skipped = 0;
        $currentNumber = $lastOrderNumber + 1;

        $wp->table('wc_orders')
            ->whereNotIn('status', ['auto-draft'])
            ->where('type', 'shop_order')
            ->orderBy('id')
            ->chunk(50, function ($wpOrders) use ($wp, &$imported, &$skipped, &$currentNumber) {
                foreach ($wpOrders as $wpOrder) {
                    $laravelUserId = $this->getLaravelUserId($wp, $wpOrder->customer_id);

                    if (! $laravelUserId && ! empty($wpOrder->billing_email)) {
                        $user = User::withoutTenantScope()->where('email', $wpOrder->billing_email)->first();
                        $laravelUserId = $user?->id;
                    }

                    if ($this->orderExists($wpOrder->id)) {
                        $skipped++;

                        continue;
                    }

                    $status = $this->statusMap[$wpOrder->status] ?? 'pending';
                    $paymentMethod = $this->paymentMethodMap[$wpOrder->payment_method] ?? 'online';
                    $paymentStatus = $this->resolvePaymentStatus($wpOrder);
                    $orderMeta = $wp->getAllMeta($wpOrder->id, 'wc_orders_meta');

                    if ($this->option('dry-run')) {
                        $imported++;

                        continue;
                    }

                    $order = new Order;
                    $order->timestamps = false;
                    $order->platform = 'main';
                    $order->order_number = (string) $currentNumber;
                    $order->user_id = $laravelUserId;
                    $order->status = $status;
                    $order->total_amount = $this->normalizeAmount($wpOrder->total_amount);
                    $order->payment_method = $paymentMethod;
                    $order->payment_status = $paymentStatus;
                    $order->notes = $wpOrder->customer_note ?: null;
                    $order->created_at = $wpOrder->date_created_gmt;
                    $order->updated_at = $wpOrder->date_updated_gmt;
                    $order->save();

                    $this->syncHesabfaMeta($order, $orderMeta);
                    $this->saveOrderMapping($wp, $wpOrder->id, $order->id);

                    $currentNumber = max($currentNumber, (int) $order->order_number) + 1;
                    $imported++;
                }
            });

        $this->newLine();
        $this->info("Import complete: $imported orders imported, $skipped skipped");

        return Command::SUCCESS;
    }

    protected function getLaravelUserId(WpDatabase $wp, int $wpCustomerId): ?int
    {
        $map = $wp->table('user_mapping')->where('wp_user_id', $wpCustomerId)->first();

        return $map?->laravel_user_id;
    }

    protected function orderExists(int $wpOrderId): bool
    {
        return DB::connection('wp_data')
            ->table('order_mapping')
            ->where('wp_order_id', $wpOrderId)
            ->exists();
    }

    protected function resolvePaymentStatus(object $wpOrder): string
    {
        if ($wpOrder->status === 'wc-completed' || $wpOrder->status === 'wc-processing') {
            $paidMeta = DB::connection('wp_data')
                ->table('wc_orders_meta')
                ->where('order_id', $wpOrder->id)
                ->where('meta_key', '_paid_date')
                ->first();

            if ($paidMeta && ! empty($paidMeta->meta_value)) {
                return 'paid';
            }

            return 'paid';
        }

        if ($wpOrder->status === 'wc-cancelled' || $wpOrder->status === 'wc-failed') {
            return 'failed';
        }

        return 'pending';
    }

    protected function normalizeAmount($amount): int
    {
        return (int) round((float) $amount);
    }

    protected function syncHesabfaMeta(Order $order, array $meta): void
    {
        $hesabfaFields = [
            '_hesabfa_contact_code' => 'hesabfa_contact_code',
            '_hesabfa_invoice_number' => 'hesabfa_invoice_number',
            '_hesabfa_invoice_reference' => 'hesabfa_invoice_reference',
            '_hesabfa_synced_at' => 'hesabfa_synced_at',
        ];

        $updateData = [];

        foreach ($hesabfaFields as $metaKey => $orderField) {
            if (isset($meta[$metaKey]) && ! empty($meta[$metaKey])) {
                $updateData[$orderField] = $meta[$metaKey];
            }
        }

        if (! empty($updateData)) {
            $order->update($updateData);
        }
    }

    protected function ensureMappingTable(WpDatabase $wp): void
    {
        $wp->ensureMappingTable('order_mapping', [
            'wp_order_id' => 'BIGINT UNSIGNED NOT NULL PRIMARY KEY',
            'laravel_order_id' => 'BIGINT UNSIGNED NOT NULL',
            'KEY laravel_order_id_idx (laravel_order_id)',
        ]);
    }

    protected function saveOrderMapping(WpDatabase $wp, int $wpOrderId, int $laravelOrderId): void
    {
        $wp->saveMapping('order_mapping', [
            'wp_order_id' => $wpOrderId,
            'laravel_order_id' => $laravelOrderId,
        ]);
    }
}
