<?php

namespace App\Console\Commands\WpMigration;

use App\Services\WpMigration\WpDatabase;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Modules\Order\Models\Order;
use Modules\Payment\Models\Payment;

class ImportPayments extends Command
{
    protected $signature = 'migrate:wp-payments
                            {--dry-run : Only show stats without inserting}';

    protected $description = 'Import WordPress payments into Laravel';

    protected array $gatewayMap = [
        'WC_Pec_Gateway' => 'pec',
        'WCDigiPay' => 'digipay',
        'kamanlend' => 'kamanlend',
        'wc_smartis_gateway' => 'smartis',
    ];

    public function handle(WpDatabase $wp): int
    {
        if (! $wp->table('order_mapping')->exists()) {
            $this->error('Order mapping table not found. Run migrate:wp-orders first.');

            return Command::FAILURE;
        }

        $totalOrders = $wp->table('order_mapping')->count();
        $this->line("Mapped orders: $totalOrders");

        $imported = 0;
        $skipped = 0;

        $wp->table('order_mapping')
            ->orderBy('wp_order_id')
            ->chunk(100, function ($mappings) use ($wp, &$imported, &$skipped) {
                foreach ($mappings as $map) {
                    $wpOrderId = $map->wp_order_id;
                    $laravelOrderId = $map->laravel_order_id;

                    if (Payment::withoutTenantScope()->where('order_id', $laravelOrderId)->exists()) {
                        $skipped++;

                        continue;
                    }

                    $wpOrder = $wp->table('wc_orders')->where('id', $wpOrderId)->first();

                    if (! $wpOrder) {
                        $skipped++;

                        continue;
                    }

                    $laravelUserId = $this->getLaravelUserId($wp, $wpOrder, $laravelOrderId);
                    $orderMeta = $wp->getAllMeta($wpOrderId, 'wc_orders_meta');
                    $pecPayment = $this->getPecPayment($wp, $wpOrderId);

                    $gateway = $this->gatewayMap[$wpOrder->payment_method] ?? ($wpOrder->payment_method ?: null);
                    $paymentStatus = $this->resolvePaymentStatus($wpOrder, $pecPayment);
                    $paidAt = $this->resolvePaidAt($wpOrder, $orderMeta);
                    $transactionId = $this->resolveTransactionId($pecPayment, $wpOrder);

                    $gatewayResponse = [];
                    if ($pecPayment) {
                        $gatewayResponse['pec'] = [
                            'id' => $pecPayment->id,
                            'RRN' => $pecPayment->RRN,
                            'status' => $pecPayment->status,
                        ];
                    }

                    if (empty($transactionId)) {
                        $transactionId = 'wp-'.$wpOrderId.'-'.bin2hex(random_bytes(4));
                    }

                    if ($this->option('dry-run')) {
                        $imported++;

                        continue;
                    }

                    try {
                        $payment = new Payment;
                        $payment->timestamps = false;
                        $payment->user_id = $laravelUserId;
                        $payment->order_id = $laravelOrderId;
                        $payment->platform = 'main';
                        $payment->transaction_id = $transactionId;
                        $payment->amount = $this->normalizeAmount($wpOrder->total_amount);
                        $payment->payment_method = $wpOrder->payment_method === 'WC_Pec_Gateway' ? 'online' : 'installment';
                        $payment->status = $paymentStatus;
                        $payment->gateway = $gateway;
                        $payment->gateway_response = ! empty($gatewayResponse) ? $gatewayResponse : null;
                        $payment->description = $wpOrder->payment_method_title ?? null;
                        $payment->paid_at = $paidAt;
                        $payment->created_at = $wpOrder->date_created_gmt;
                        $payment->updated_at = $wpOrder->date_updated_gmt;
                        $payment->save();
                    } catch (QueryException $e) {
                        if (str_contains($e->getMessage(), 'Duplicate entry')) {
                            $this->warn("Duplicate payment for WP order $wpOrderId, skipping");
                            $skipped++;

                            continue;
                        }
                        throw $e;
                    }

                    $imported++;
                }
            });

        $this->newLine();
        $this->info("Import complete: $imported payments imported, $skipped skipped");

        return Command::SUCCESS;
    }

    protected function getLaravelUserId(WpDatabase $wp, object $wpOrder, int $laravelOrderId): ?int
    {
        $order = Order::withoutTenantScope()->find($laravelOrderId);

        if ($order && $order->user_id) {
            return $order->user_id;
        }

        if ($wpOrder->customer_id) {
            $map = $wp->table('user_mapping')->where('wp_user_id', $wpOrder->customer_id)->first();

            return $map?->laravel_user_id;
        }

        return null;
    }

    protected function getPecPayment(WpDatabase $wp, int $wpOrderId): ?object
    {
        return $wp->table('pec_payments')->where('order_id', $wpOrderId)->first();
    }

    protected function resolvePaymentStatus(object $wpOrder, ?object $pecPayment): string
    {
        if ($pecPayment) {
            if ($pecPayment->status === '0' || $pecPayment->status === '1') {
                return 'paid';
            }

            if ($pecPayment->status === '-1') {
                return 'failed';
            }
        }

        return match ($wpOrder->status) {
            'wc-completed', 'wc-processing', 'wc-delivered' => 'paid',
            'wc-cancelled', 'wc-failed' => 'failed',
            default => 'pending',
        };
    }

    protected function resolvePaidAt(object $wpOrder, array $meta): ?string
    {
        if (isset($meta['_paid_date']) && ! empty($meta['_paid_date'])) {
            return $meta['_paid_date'];
        }

        return match ($wpOrder->status) {
            'wc-completed', 'wc-processing', 'wc-delivered' => $wpOrder->date_created_gmt,
            default => null,
        };
    }

    protected function resolveTransactionId(?object $pecPayment, object $wpOrder): ?string
    {
        if ($pecPayment && ! empty($pecPayment->RRN)) {
            return $pecPayment->RRN;
        }

        if (! empty($wpOrder->transaction_id)) {
            return $wpOrder->transaction_id;
        }

        return null;
    }

    protected function normalizeAmount($amount): int
    {
        return (int) round((float) $amount);
    }
}
