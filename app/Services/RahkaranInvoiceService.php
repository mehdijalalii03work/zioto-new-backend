<?php

namespace App\Services;

use App\Models\HesabfaSyncLog;
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
        $serialTrackingEnabled = setting('rahkaran_serial_tracking_enabled', false)
            || setting('inventory_source', 'hesabfa') === 'rahkaran';

        if ($order->payment_status !== 'paid' || ! in_array($order->status, ['confirmed', 'completed'], true)) {
            return ['success' => false, 'message' => 'فقط سفارش پرداخت‌شده و تأییدشده یا تکمیل‌شده به راهکاران ارسال می‌شود'];
        }

        if (! setting('send_invoices_to_rahkaran', false)) {
            return ['success' => false, 'message' => 'ارسال فاکتور به راهکاران غیرفعال است'];
        }

        if (! $this->rahkaran->isConfigured()) {
            return $this->fail($order, 'تنظیمات اتصال راهکاران کامل نیست');
        }

        $claim = DB::transaction(function () use ($order): array {
            $lockedOrder = Order::query()->lockForUpdate()->find($order->id);
            if (! $lockedOrder) {
                return ['order' => null, 'message' => 'سفارش یافت نشد'];
            }

            if ($lockedOrder->rahkaran_invoice_id && $lockedOrder->rahkaran_sync_status === 'success') {
                return ['order' => null, 'message' => 'سفارش قبلاً به راهکاران ارسال شده'];
            }

            if ($lockedOrder->rahkaran_sync_status === 'processing') {
                return ['order' => null, 'message' => 'ارسال این سفارش به راهکاران در حال انجام است'];
            }

            $lockedOrder->forceFill(['rahkaran_sync_status' => 'processing', 'rahkaran_sync_error' => null])->saveQuietly();

            return ['order' => $lockedOrder, 'message' => null];
        });
        if (! $claim['order']) {
            return ['success' => false, 'message' => $claim['message']];
        }
        $order = $claim['order'];

        try {
            $order->load(['items.product.baseProduct', 'items.rahkaranSerials', 'user', 'address', 'shipping']);
            if ($serialTrackingEnabled) {
                $this->inventory->reserveForOrder($order);
                $order->load('items.rahkaranSerials');
            }

            $customerId = $order->rahkaran_customer_id ?: $this->findCustomerId($order);
            $addressId = $this->ensureCustomerAddress($order, $customerId);
            $invoiceId = (int) $order->rahkaran_invoice_id;
            if ($invoiceId === 0) {
                $invoiceId = $this->rahkaran->placeInvoice($this->buildInvoice($order, $customerId, $addressId));
                $order->forceFill(['rahkaran_customer_id' => $customerId, 'rahkaran_invoice_id' => $invoiceId])->saveQuietly();
            }
            $this->inventory->finalizeOrder($order);
            $trackingFactors = $this->buildTrackingFactors($order, $invoiceId);
            if ($trackingFactors !== []) {
                $this->rahkaran->addInvoiceTrackingFactors($invoiceId, $trackingFactors);
            }

            $order->forceFill([
                'rahkaran_customer_id' => $customerId,
                'rahkaran_invoice_id' => $invoiceId,
                'rahkaran_synced_at' => now(),
                'rahkaran_sync_status' => 'success',
                'rahkaran_sync_error' => null,
            ])->saveQuietly();

            HesabfaSyncLog::query()->create([
                'order_id' => $order->id,
                'sync_type' => 'rahkaran_invoice',
                'status' => 'success',
                'request_data' => ['order_number' => $order->order_number],
                'response_data' => ['invoice_id' => $invoiceId, 'customer_id' => $customerId],
            ]);

            return ['success' => true, 'message' => 'فاکتور به راهکاران ارسال شد', 'invoice_id' => $invoiceId];
        } catch (\Throwable $exception) {
            return $this->fail($order, $exception->getMessage());
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

    private function buildInvoice(Order $order, int $customerId, ?int $addressId): array
    {
        $productRefs = $this->settings->get('product_refs') ?? [];
        $unitRefs = $this->settings->get('unit_refs') ?? [];
        $items = [];

        foreach ($order->items as $index => $item) {
            if (! $item->product) {
                throw new RuntimeException("محصول سفارش {$item->product_name} حذف شده است.");
            }

            $product = $item->product->baseProduct ?? $item->product;
            $partRef = $productRefs[$item->product->sku] ?? $productRefs[$product->sku] ?? null;
            $unitRef = $unitRefs[$item->product->sku] ?? $unitRefs[$product->sku] ?? null;
            if (! $partRef || ! $unitRef) {
                throw new RuntimeException("نگاشت کالا یا واحد راهکاران برای SKU {$item->product->sku} تنظیم نشده است.");
            }

            $items[] = [
                'id' => '-1',
                'index' => $index + 1,
                'productId' => (string) $partRef,
                'quantity' => (string) (int) $item->quantity,
                'salesAreaId' => (string) $this->settings->get('sales_area_id'),
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

        if ($items === []) {
            throw new RuntimeException('سفارش قلمی برای ثبت فاکتور ندارد.');
        }

        $notes = json_decode((string) $order->notes, true) ?: [];
        $additions = (int) ($order->shipping?->shipping_cost ?? 0) + (int) ($notes['installment_fee'] ?? 0);
        $itemsTotal = array_sum(array_map(static fn ($item): int => (int) $item->subtotal, $order->items->all()));

        return [
            'id' => '-1',
            'recipientType' => (int) $this->settings->get('invoice_recipient_type'),
            'customerId' => (string) $customerId,
            'oneTimeCustomerId' => '',
            'salesAreaId' => (string) $this->settings->get('sales_area_id'),
            'salesOfficeId' => (string) $this->settings->get('sales_office_id'),
            'salesTypeId' => (string) $this->settings->get('sales_type_id'),
            'date' => '/Date('.now()->getTimestampMs().now()->format('O').')/',
            'addressId' => (string) ($addressId ?? ''),
            'currencyId' => (string) $this->settings->get('currency_id'),
            'payerType' => (int) $this->settings->get('invoice_payer_type'),
            'plantId' => (string) $this->settings->get('plant_id'),
            'inventoryId' => (string) $this->settings->get('inventory_id'),
            'price' => (string) ($itemsTotal + $additions),
            'netPrice' => (string) $order->total_amount,
            'additionAmount' => (string) $additions,
            'reductionAmount' => (string) $order->discount_amount,
            'description' => 'سفارش سایت زیوتو '.$order->order_number,
            'items' => $items,
        ];
    }

    private function buildTrackingFactors(Order $order, int $invoiceId): array
    {
        $factorRef = (int) $this->settings->get('tracking_factor_ref');
        $hasAssignedSerials = $order->items->contains(fn ($item) => $item->rahkaranSerials->isNotEmpty());
        if ($factorRef <= 0 && $hasAssignedSerials) {
            throw new RuntimeException('شناسه عامل کنترل موجودی سریال در تنظیمات راهکاران وارد نشده است.');
        }

        if ($factorRef <= 0) {
            return [];
        }

        $productRefs = $this->settings->get('product_refs') ?? [];
        $unitRefs = $this->settings->get('unit_refs') ?? [];
        $factors = [];

        foreach ($order->items as $rowIndex => $item) {
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
            $partRef = $productRefs[$item->product?->sku] ?? $productRefs[$stockProduct?->sku] ?? null;
            $unitRef = $unitRefs[$item->product?->sku] ?? $unitRefs[$stockProduct?->sku] ?? null;

            foreach ($serials as $serialIndex => $serial) {
                $factors[] = [
                    'ID' => 0,
                    '_MasterRef' => $invoiceId,
                    'Radif' => $rowIndex + 1,
                    'MahsolRef' => (int) $partRef,
                    'VahedRef' => (int) $unitRef,
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
