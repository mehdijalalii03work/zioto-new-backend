<?php

namespace App\Services;

use App\Models\HesabfaSyncLog;
use App\Models\RahkaranInvoice;
use Closure;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Modules\Order\Models\Order;
use RuntimeException;

class RahkaranInvoiceService
{
    public function __construct(
        private RahkaranService $rahkaran,
        private RahkaranInventoryService $inventory,
        private RahkaranSettings $settings,
    ) {}

    public function syncOrder(Order $order, bool $force = false): array
    {
        $lock = Cache::lock("rahkaran:invoice:{$order->id}", 900);
        if (! $lock->get()) {
            return ['success' => false, 'message' => 'ارسال این سفارش به راهکاران در حال انجام است'];
        }

        try {
            $order->refresh();
            if ($order->payment_status !== 'paid' || ! in_array($order->status, ['confirmed', 'completed'], true)) {
                return ['success' => false, 'message' => 'فقط سفارش پرداخت‌شده و تأییدشده یا تکمیل‌شده به راهکاران ارسال می‌شود'];
            }
            if (! setting('send_invoices_to_rahkaran', false)) {
                return ['success' => false, 'message' => 'ارسال فاکتور به راهکاران غیرفعال است'];
            }

            $invoices = $order->rahkaranInvoices()->orderBy('sales_area_id')->get();
            if ($order->rahkaran_sync_status === 'success' || ($order->rahkaran_invoice_id && $invoices->isEmpty())) {
                return ['success' => true, 'message' => 'سفارش قبلاً به راهکاران ارسال شده'];
            }
            if (! $this->rahkaran->isConfigured()) {
                throw new RuntimeException('تنظیمات اتصال راهکاران کامل نیست');
            }
            $order->forceFill(['rahkaran_sync_status' => 'processing', 'rahkaran_sync_error' => null])->saveQuietly();
            $order->load(['items.product.baseProduct', 'items.rahkaranSerials', 'user', 'address', 'shipping']);

            if ($invoices->isEmpty()) {
                $this->prepareInvoices($order);
                $invoices = $order->rahkaranInvoices()->orderBy('sales_area_id')->get();
            }

            foreach ($invoices as $invoice) {
                $this->sendInvoice($invoice);
            }

            $this->inventory->finalizeOrder($order);
            $invoiceIds = $invoices->pluck('remote_id')->all();
            $order->forceFill([
                'rahkaran_synced_at' => now(),
                'rahkaran_sync_status' => 'success',
                'rahkaran_sync_error' => null,
            ])->saveQuietly();

            HesabfaSyncLog::query()->create([
                'order_id' => $order->id,
                'sync_type' => 'rahkaran_invoice',
                'status' => 'success',
                'request_data' => ['order_number' => $order->order_number],
                'response_data' => ['invoice_ids' => $invoiceIds, 'customer_id' => $order->rahkaran_customer_id],
            ]);

            return ['success' => true, 'message' => 'فاکتورهای راهکاران ثبت شدند', 'invoice_ids' => $invoiceIds];
        } catch (\Throwable $exception) {
            return $this->fail($order, $exception->getMessage());
        } finally {
            $lock->release();
        }
    }

    private function prepareInvoices(Order $order): void
    {
        $isTapsi = filled($order->tapsi_order_id);
        $notes = json_decode((string) $order->notes, true) ?: [];
        if (! $isTapsi && ((int) $order->discount_amount !== 0
            || (int) ($order->shipping?->shipping_cost ?? 0) !== 0
            || (int) ($notes['installment_fee'] ?? 0) !== 0)) {
            throw new RuntimeException('ثبت سند حوزه آنلاین - خدمات برای هزینه ارسال، کارمزد یا تخفیف هنوز آماده نیست.');
        }

        $channel = $isTapsi ? 'tapsi' : 'online';
        if (! $isTapsi && $order->payments()->where('status', 'paid')->latest('id')->first()?->gateway === 'digipay') {
            $channel = 'digipay';
        }

        if ($order->items->isEmpty()) {
            throw new RuntimeException('سفارش قلمی برای ثبت فاکتور ندارد.');
        }

        $groups = $order->items->groupBy(function ($item) use ($channel): int {
            $metal = $item->product?->metal_type?->value;
            $area = (int) config("rahkaran.sales_areas.{$channel}.{$metal}");
            if ($area <= 0) {
                throw new RuntimeException("حوزه فروش راهکاران برای {$item->product_name} مشخص نیست.");
            }

            return $area;
        });

        $plans = [];
        foreach ($groups as $area => $items) {
            $plans[$area]['payload'] = $this->buildInvoice($order, $items, $area);
        }

        if (setting('rahkaran_serial_tracking_enabled', false) || setting('inventory_source', 'hesabfa') === 'rahkaran') {
            $this->inventory->reserveForOrder($order);
            $order->items->load('rahkaranSerials');
        }
        foreach ($groups as $area => $items) {
            $plans[$area]['tracking_factors'] = $this->buildTrackingFactors($items, $plans[$area]['payload']['items']);
        }

        $customerId = $order->rahkaran_customer_id ?: $this->findCustomerId($order);
        $addressId = $this->ensureCustomerAddress($order, $customerId);

        DB::transaction(function () use ($order, $plans, $customerId, $addressId): void {
            foreach ($plans as $area => $plan) {
                $plan['payload']['customerId'] = (string) $customerId;
                $plan['payload']['addressId'] = (string) ($addressId ?? '');
                $order->rahkaranInvoices()->create(['sales_area_id' => $area, ...$plan]);
            }
            $order->forceFill(['rahkaran_customer_id' => $customerId])->saveQuietly();
        });
    }

    private function sendInvoice(RahkaranInvoice $invoice): void
    {
        foreach (['status', 'serial_status'] as $stage) {
            if (in_array($invoice->{$stage}, ['submitting', 'reconciliation'], true)) {
                $message = "نتیجه ثبت حوزه {$invoice->sales_area_id} باید در راهکاران تطبیق داده شود.";
                $invoice->update([$stage => 'reconciliation', 'last_error' => $message]);

                throw new RuntimeException($message);
            }
        }

        if (! $invoice->remote_id) {
            $this->submit($invoice, 'status', function () use ($invoice): array {
                return ['remote_id' => $this->rahkaran->placeInvoice($invoice->payload)];
            });
        }
        if ($invoice->serial_status !== 'success') {
            if ($invoice->tracking_factors === []) {
                $invoice->update(['serial_status' => 'success', 'last_error' => null]);

                return;
            }

            $this->submit($invoice, 'serial_status', function () use ($invoice): array {
                $factors = array_map(fn (array $factor): array => [...$factor, '_MasterRef' => $invoice->remote_id], $invoice->tracking_factors);
                $this->rahkaran->addInvoiceTrackingFactors($invoice->remote_id, $factors);

                return [];
            });
        }
    }

    private function submit(RahkaranInvoice $invoice, string $stage, Closure $request): void
    {
        $invoice->update([$stage => 'submitting']);
        try {
            $invoice->update([...$request(), $stage => 'success', 'last_error' => null]);
        } catch (\Throwable $exception) {
            $rejected = $exception instanceof RequestException
                && in_array($exception->response->status(), [400, 401, 403, 404, 422, 429], true);
            $message = $rejected
                ? "ثبت حوزه {$invoice->sales_area_id} توسط راهکاران رد شد (HTTP {$exception->response->status()})."
                : "نتیجه ثبت حوزه {$invoice->sales_area_id} باید در راهکاران تطبیق داده شود.";
            $invoice->update([$stage => $rejected ? 'failed' : 'reconciliation', 'last_error' => $message]);

            throw new RuntimeException($message, previous: $exception);
        }
    }

    private function findCustomerId(Order $order): int
    {
        $nationalId = $order->user?->national_code ?? $order->address?->receiver_national_code;
        if (! $nationalId) {
            throw new RuntimeException('کد ملی مشتری برای یافتن حساب راهکاران موجود نیست.');
        }

        $customerId = $this->rahkaran->findCustomerIdByNationalId($nationalId);
        if (! $customerId) {
            throw new RuntimeException('مشتری با این کد ملی در راهکاران پیدا نشد.');
        }

        return $customerId;
    }

    private function ensureCustomerAddress(Order $order, int $customerId): ?int
    {
        $address = $order->address;
        if (! $address || $address->rahkaran_address_id) {
            return $address?->rahkaran_address_id;
        }

        $regionalDivisionRef = ($this->settings->get('regional_division_refs') ?? [])[$address->city_id] ?? null;
        if (! $regionalDivisionRef) {
            throw new RuntimeException("نگاشت شهر سفارش به ناحیه راهکاران برای شهر {$address->city_id} تنظیم نشده است.");
        }

        $addressId = $this->rahkaran->createCustomerAddress($customerId, [
            'regional_division_ref' => (int) $regionalDivisionRef,
            'details' => $address->full_address,
            'phone' => (string) $address->receiver_phone,
            'zip_code' => (string) $address->postal_code,
            'name' => (string) ($address->label ?: 'آدرس سفارش سایت'),
        ]);
        if (! $addressId) {
            throw new RuntimeException('شناسه آدرس مشتری از راهکاران دریافت نشد.');
        }

        $address->forceFill(['rahkaran_address_id' => $addressId])->save();

        return $addressId;
    }

    private function buildInvoice(Order $order, Collection $group, int $area): array
    {
        $unitRefs = $this->settings->get('unit_refs') ?? [];
        $items = [];

        foreach ($group->values() as $index => $item) {
            if (! $item->product) {
                throw new RuntimeException("محصول سفارش {$item->product_name} حذف شده است.");
            }

            $product = $item->product->baseProduct ?? $item->product;
            $partRef = $item->product->rahkaran_part_ref ?? $product->rahkaran_part_ref;
            $unitRef = $item->product->rahkaran_unit_ref
                ?? $product->rahkaran_unit_ref
                ?? $unitRefs[$item->product->sku]
                ?? $unitRefs[$product->sku]
                ?? null;
            if (! $partRef || ! $unitRef) {
                throw new RuntimeException("نگاشت کالا یا واحد راهکاران برای SKU {$item->product->sku} تنظیم نشده است.");
            }

            $items[] = [
                'id' => '-1',
                'index' => $index + 1,
                'productId' => (string) $partRef,
                'quantity' => (string) (int) $item->quantity,
                'salesAreaId' => (string) $area,
                'type' => 1,
                'fee' => (string) $item->product_price,
                'price' => (string) $item->subtotal,
                'netPrice' => (string) $item->subtotal,
                'additionAmount' => '0',
                'reductionAmount' => '0',
                'inventoryID' => (string) $this->settings->get('inventory_id'),
                'plantID' => (string) $this->settings->get('plant_id'),
                'unitId' => (string) $unitRef,
                'description' => $item->product_name,
                'productPackQuantity' => '0',
            ];
        }

        $itemsTotal = $group->sum(fn ($item): int => (int) $item->subtotal);

        return [
            'id' => '-1',
            'recipientType' => (int) $this->settings->get('invoice_recipient_type'),
            'customerId' => '',
            'oneTimeCustomerId' => '',
            'salesAreaId' => (string) $area,
            'salesOfficeId' => (string) $this->settings->get('sales_office_id'),
            'salesTypeId' => (string) $this->settings->get('sales_type_id'),
            'date' => '/Date('.now()->getTimestampMs().now()->format('O').')/',
            'addressId' => '',
            'currencyId' => (string) $this->settings->get('currency_id'),
            'payerType' => (int) $this->settings->get('invoice_payer_type'),
            'plantId' => (string) $this->settings->get('plant_id'),
            'inventoryId' => (string) $this->settings->get('inventory_id'),
            'price' => (string) $itemsTotal,
            'netPrice' => (string) $itemsTotal,
            'additionAmount' => '0',
            'reductionAmount' => '0',
            'description' => 'سفارش زیوتو '.$order->order_number.' / '.$area,
            'items' => $items,
        ];
    }

    private function buildTrackingFactors(Collection $group, array $invoiceItems): array
    {
        $factorRef = (int) $this->settings->get('tracking_factor_ref');
        $hasAssignedSerials = $group->contains(fn ($item) => $item->rahkaranSerials->isNotEmpty());
        if ($factorRef <= 0 && $hasAssignedSerials) {
            throw new RuntimeException('شناسه عامل کنترل موجودی سریال در تنظیمات راهکاران وارد نشده است.');
        }

        $factors = [];

        foreach ($group->values() as $rowIndex => $item) {
            $serials = $item->rahkaranSerials->pluck('serial')->values();
            $stockProduct = $item->product?->baseProduct ?? $item->product;
            $serialAllocationEnabled = setting('rahkaran_serial_tracking_enabled', false)
                || setting('inventory_source', 'hesabfa') === 'rahkaran';
            if ($serialAllocationEnabled && $stockProduct?->rahkaran_serial_tracked) {
                $required = (int) $item->quantity * ($item->product?->pack_size ?? 1);
                if ($serials->count() < $required) {
                    throw new RuntimeException("برای قلم سفارش {$item->product_name} سریال کافی تخصیص داده نشده است.");
                }
            }

            if ($serials->isEmpty()) {
                continue;
            }
            $line = $invoiceItems[$rowIndex];

            foreach ($serials as $serial) {
                $factors[] = [
                    'ID' => 0,
                    'Radif' => $rowIndex + 1,
                    'MahsolRef' => (int) $line['productId'],
                    'VahedRef' => (int) $line['unitId'],
                    'AmelKontrolMojodi' => (string) $serial,
                    'Meghdar' => 1,
                    'Sys__EntityModificationState' => 'New',
                    'sys__entityCode' => $factorRef,
                ];
            }
        }

        return $factors;
    }

    private function fail(Order $order, string $message): array
    {
        $order->forceFill(['rahkaran_sync_status' => 'failed', 'rahkaran_sync_error' => $message])->saveQuietly();
        HesabfaSyncLog::query()->create([
            'order_id' => $order->id,
            'sync_type' => 'rahkaran_invoice',
            'status' => 'failed',
            'request_data' => ['order_number' => $order->order_number],
            'error_message' => $message,
        ]);

        return ['success' => false, 'message' => $message];
    }
}
