<?php

namespace App\Observers;

use App\Enums\Product\MetalType;
use App\Models\HesabfaSyncLog;
use App\Models\Setting;
use App\Services\HesabfaService;
use App\Services\InstallmentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Order\Models\Order;

class HesabfaObserver
{
    public function __construct(
        private HesabfaService $hesabfa,
    ) {}

    public function updated(Order $order): void
    {
        if (! config('hesabfa.auto_sync')) {
            return;
        }

        if ($order->hesabfa_synced_at && ! $order->wasChanged('status')) {
            return;
        }

        $syncStatuses = config('hesabfa.sync_statuses', ['confirmed', 'processing']);

        if (! in_array($order->status, $syncStatuses)) {
            return;
        }

        if ($order->hesabfa_synced_at) {
            return;
        }

        $this->syncOrder($order);
    }

    public function syncOrder(Order $order, bool $force = false): array
    {
        if (! $this->hesabfa->isConfigured()) {
            return ['success' => false, 'message' => 'تنظیمات حسابفا یافت نشد'];
        }

        if ($order->hesabfa_synced_at && ! $force) {
            return ['success' => false, 'message' => 'سفارش قبلاً به حسابفا ارسال شده'];
        }

        $order->load(['items.product', 'shipping.shippingMethod', 'address.province', 'address.city', 'user']);

        try {
            DB::beginTransaction();

            $contactResult = $this->syncContact($order);
            if (! $contactResult['success']) {
                DB::rollBack();
                Log::channel('hesabfa')->error('Hesabfa contact sync failed', [
                    'order_id' => $order->id,
                    'message' => $contactResult['message'],
                ]);
                $this->log($order, 'contact', 'failed', null, $contactResult['message']);

                return $contactResult;
            }

            $invoiceResult = $this->syncInvoice($order, $contactResult['contact_code']);
            if (! $invoiceResult['success']) {
                DB::rollBack();
                Log::channel('hesabfa')->error('Hesabfa invoice sync failed', [
                    'order_id' => $order->id,
                    'message' => $invoiceResult['message'],
                ]);
                $this->log($order, 'invoice', 'failed', null, $invoiceResult['message']);

                return $invoiceResult;
            }

            $order->forceFill([
                'hesabfa_contact_code' => $contactResult['contact_code'],
                'hesabfa_invoice_number' => $invoiceResult['invoice_number'],
                'hesabfa_invoice_reference' => $invoiceResult['reference'],
                'hesabfa_synced_at' => now(),
            ])->save();

            DB::commit();

            $this->log($order, 'full_sync', 'success', [
                'contact_code' => $contactResult['contact_code'],
                'invoice_number' => $invoiceResult['invoice_number'],
                'reference' => $invoiceResult['reference'],
            ]);

            $order->addNote(
                "فاکتور با موفقیت به حسابفا ارسال شد.\nشماره فاکتور: {$invoiceResult['invoice_number']}\nکد مرجع: {$invoiceResult['reference']}",
                'hesabfa',
                true
            );

            return ['success' => true, 'message' => 'سفارش با موفقیت به حسابفا ارسال شد'];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::channel('hesabfa')->error('Hesabfa sync error', [
                'order_id' => $order->id,
                'message' => $e->getMessage(),
            ]);
            $this->log($order, 'full_sync', 'failed', null, $e->getMessage());

            $order->addNote(
                "خطا در ارسال به حسابفا: {$e->getMessage()}",
                'hesabfa'
            );

            return ['success' => false, 'message' => 'خطا در ارسال به حسابفا: '.$e->getMessage()];
        }
    }

    private function syncContact(Order $order): array
    {
        $user = $order->user;
        $nationalCode = $this->extractNationalCode($order);

        if (! $nationalCode) {
            return ['success' => false, 'message' => 'کد ملی مشتری یافت نشد'];
        }

        $existing = $this->hesabfa->findContactByNationalCode($nationalCode);

        $contactData = $this->buildContactData($order, $nationalCode, $existing);

        $result = $this->hesabfa->saveContact($contactData);

        if (! $result['success']) {
            Log::channel('hesabfa')->error('Hesabfa saveContact API error', [
                'order_id' => $order->id,
                'national_code' => $nationalCode,
                'error' => $result['error'],
                'payload' => $contactData,
            ]);

            return ['success' => false, 'message' => 'خطا در ذخیره مشتری: '.$result['error']];
        }

        $contactCode = $result['data']['Result']['Code'] ?? $result['data']['Result'] ?? $existing['Code'] ?? null;

        if (is_array($contactCode)) {
            $contactCode = $contactCode['Code'] ?? null;
        }

        if (! $contactCode) {
            $recheck = $this->hesabfa->findContactByNationalCode($nationalCode);
            $contactCode = $recheck['Code'] ?? null;
        }

        if (! $contactCode) {
            return ['success' => false, 'message' => 'کد مشتری از حسابفا دریافت نشد'];
        }

        return ['success' => true, 'contact_code' => $contactCode];
    }

    private function buildContactData(Order $order, string $nationalCode, ?array $existing): array
    {
        $user = $order->user;

        $contactData = [
            'FirstName' => $user?->first_name ?? '',
            'LastName' => $user?->last_name ?? '',
            'Name' => trim(($user?->first_name ?? '').' '.($user?->last_name ?? '')),
            'DisplayName' => $user?->name ?? $order->user_address_id ? 'مشتری شماره '.$order->id : '',
            'Title' => trim(($user?->first_name ?? '').' '.($user?->last_name ?? '')),
            'ContactType' => 1,
            'NationalCode' => $nationalCode,
            'Mobile' => $this->normalizeMobile($user?->phone ?? ''),
            'Country' => 'ایران',
            'IsCustomer' => true,
        ];

        $nodeName = config('hesabfa.customer_node');
        $nodeFamily = config('hesabfa.customer_family');

        if ($nodeName) {
            $contactData['NodeName'] = $nodeName;
        }

        if ($nodeFamily) {
            $contactData['NodeFamily'] = $nodeFamily;
        }

        if ($existing) {
            $contactData['Code'] = $existing['Code'];
        } else {
            $contactData['TaxType'] = 8;
        }

        if ($order->address) {
            $contactData['State'] = $order->address->province?->name ?? '';
            $contactData['City'] = $order->address->city?->name ?? '';
            $contactData['Address'] = $order->address->full_address;
            $contactData['PostalCode'] = $order->address->postal_code ?? '';
        }

        return $contactData;
    }

    private function syncInvoice(Order $order, string $contactCode): array
    {
        $preValidate = $this->preValidateInvoiceItems($order);
        if (! $preValidate['success']) {
            return $preValidate;
        }

        $reference = $this->buildInvoiceReference($order);
        $date = $this->buildInvoiceDate($order);

        $items = $this->buildInvoiceItems($order);

        if ($items === false) {
            return ['success' => false, 'message' => 'کد محصول حسابفا یافت نشد'];
        }

        $invoiceData = $this->buildInvoicePayload($order, $reference, $date, $contactCode, $items);

        return $this->saveAndConfirmInvoice($order, $invoiceData, $reference);
    }

    private function preValidateInvoiceItems(Order $order): array
    {
        if ($order->shipping && $order->shipping->shipping_cost > 0) {
            $shippingCode = config('hesabfa.shipping_item_code');
            if (empty($shippingCode)) {
                return ['success' => false, 'message' => 'کد کالای هزینه ارسال در تنظیمات وارد نشده است'];
            }
            $shippingItem = $this->hesabfa->getItemByCode($shippingCode);
            if (! $shippingItem || isset($shippingItem['error'])) {
                return ['success' => false, 'message' => "کد کالای هزینه ارسال '{$shippingCode}' در حسابفا یافت نشد"];
            }
        }

        if ($order->payment_method === 'installment') {
            $feeCode = config('hesabfa.installment_fee_item_code');
            if (empty($feeCode)) {
                return ['success' => false, 'message' => 'کد کالای کارمزد خرید اقساطی در تنظیمات وارد نشده است'];
            }
            $feeItem = $this->hesabfa->getItemByCode($feeCode);
            if (! $feeItem || isset($feeItem['error'])) {
                return ['success' => false, 'message' => "کد کالای کارمزد خرید اقساطی '{$feeCode}' در حسابفا یافت نشد"];
            }
        }

        return ['success' => true];
    }

    private function buildInvoiceReference(Order $order): string
    {
        $totalInRials = (int) $order->total_amount;
        $tenMillions = (int) floor($totalInRials / 10_000_000);

        return "{$order->id}-{$tenMillions}";
    }

    private function buildInvoiceDate(Order $order): string
    {
        return config('hesabfa.use_current_date')
            ? now()->format('Y-m-d H:i:s')
            : $order->created_at->format('Y-m-d H:i:s');
    }

    private function buildInvoiceItems(Order $order): array|false
    {
        $items = [];
        $rowNumber = 1;

        $productItems = $this->buildProductItems($order, $rowNumber);
        if ($productItems === false) {
            return false;
        }
        $items = array_merge($items, $productItems);
        $rowNumber += count($productItems);

        $shippingItem = $this->buildShippingItem($order, $rowNumber);
        if ($shippingItem) {
            $items[] = $shippingItem;
            $rowNumber++;
        }

        $installmentItem = $this->buildInstallmentFeeItem($order, $rowNumber);
        if ($installmentItem) {
            $items[] = $installmentItem;
        }

        return $items;
    }

    private function buildProductItems(Order $order, int $startRowNumber): array|false
    {
        $items = [];
        $rowNumber = $startRowNumber;
        $skuCache = [];
        $missingSkus = [];

        foreach ($order->items as $orderItem) {
            $product = $orderItem->product;
            $sku = $product?->sku ?? '';

            if (! isset($skuCache[$sku])) {
                $skuCache[$sku] = $this->findHesabfaItemCode($sku);
            }

            $itemCode = $skuCache[$sku];

            if (! $itemCode) {
                $missingSkus[] = $sku;

                continue;
            }

            $price = (int) $orderItem->product_price;
            $tax = 0;
            $unitPrice = $price;

            $metalType = $product?->metal_type;
            $priceBoardItem = $product?->price_board_item ?? '';
            $isSilver = $metalType === MetalType::Silver
                || str_starts_with($priceBoardItem, 'Silver');

            if ($isSilver) {
                $taxRate = (float) Setting::getValue('tax_silver', 10);
                if ($taxRate > 0) {
                    $unitPrice = (int) round($price / (1 + $taxRate / 100));
                    $tax = $price - $unitPrice;
                }
            }

            $items[] = [
                'rowNumber' => $rowNumber++,
                'description' => $orderItem->product_name,
                'itemCode' => $itemCode,
                'unit' => config('hesabfa.default_unit', 'عدد'),
                'quantity' => $orderItem->quantity,
                'unitPrice' => (int) $unitPrice,
                'discount' => $this->calculateItemDiscount($orderItem),
                'tax' => (int) $tax,
            ];
        }

        if (! empty($missingSkus)) {
            Log::channel('hesabfa')->error('Hesabfa sync: SKUs not found in Hesabfa', [
                'order_id' => $order->id,
                'missing_skus' => $missingSkus,
            ]);

            return false;
        }

        return $items;
    }

    private function calculateItemDiscount(object $orderItem): int
    {
        if ($orderItem->subtotal > $orderItem->quantity * $orderItem->product_price) {
            return (int) ($orderItem->subtotal - ($orderItem->quantity * $orderItem->product_price));
        }

        return 0;
    }

    private function buildShippingItem(Order $order, int $rowNumber): ?array
    {
        if (! $order->shipping || $order->shipping->shipping_cost <= 0) {
            return null;
        }

        $shippingCode = config('hesabfa.shipping_item_code');
        if (! $shippingCode) {
            return null;
        }

        return [
            'rowNumber' => $rowNumber,
            'description' => 'هزینه حمل و نقل',
            'itemCode' => $shippingCode,
            'unit' => config('hesabfa.default_unit', 'عدد'),
            'quantity' => 1,
            'unitPrice' => (int) $order->shipping->shipping_cost,
            'discount' => 0,
            'tax' => 0,
        ];
    }

    private function buildInstallmentFeeItem(Order $order, int $rowNumber): ?array
    {
        if ($order->payment_method !== 'installment') {
            return null;
        }

        $installmentFeeCode = config('hesabfa.installment_fee_item_code');
        if (! $installmentFeeCode) {
            return null;
        }

        $baseAmount = $order->items->sum('subtotal') + ($order->shipping?->shipping_cost ?? 0);
        $totalIncludingTax = InstallmentService::calculateFee((int) $baseAmount);

        if ($totalIncludingTax <= 0) {
            return null;
        }

        $unitPrice = (int) round($totalIncludingTax / 1.10);
        $taxOnCommission = $totalIncludingTax - $unitPrice;

        return [
            'rowNumber' => $rowNumber,
            'description' => 'کارمزد خرید اقساطی ('.InstallmentService::FEE_PERCENT.'٪)',
            'itemCode' => $installmentFeeCode,
            'unit' => config('hesabfa.default_unit', 'عدد'),
            'quantity' => 1,
            'unitPrice' => $unitPrice,
            'discount' => 0,
            'tax' => $taxOnCommission,
        ];
    }

    private function buildInvoicePayload(Order $order, string $reference, string $date, string $contactCode, array $items): array
    {
        return [
            'reference' => $reference,
            'date' => $date,
            'dueDate' => $date,
            'contactCode' => $contactCode,
            'invoiceType' => 0,
            'status' => config('hesabfa.draft_invoice', true) ? 0 : 1,
            'project' => config('hesabfa.default_project', 'سایت ZIOTO'),
            'currency' => 'IRR',
            'currencyRate' => 1,
            'contactTitle' => $order->user?->name ?? '',
            'note' => "سفارش {$order->id}",
            'invoiceItems' => $items,
            'Freight' => 0,
        ];
    }

    private function saveAndConfirmInvoice(Order $order, array $invoiceData, string $reference): array
    {
        $result = $this->hesabfa->saveInvoice($invoiceData);

        if (! $result['success']) {
            Log::channel('hesabfa')->error('Hesabfa saveInvoice API error', [
                'order_id' => $order->id,
                'reference' => $reference,
                'error' => $result['error'],
            ]);

            return ['success' => false, 'message' => 'خطا در ذخیره فاکتور: '.$result['error']];
        }

        $invoiceNumber = $result['data']['Result']['Number'] ?? $result['data']['Result'] ?? null;

        if (is_array($invoiceNumber)) {
            $invoiceNumber = $invoiceNumber['Number'] ?? $invoiceNumber['InvoiceNumber'] ?? null;
        }

        if (! $invoiceNumber) {
            $existing = $this->hesabfa->findInvoiceByReference($reference);
            $invoiceNumber = $existing['InvoiceNumber'] ?? null;
        }

        if (config('hesabfa.enable_warehouse_receipt') && $invoiceNumber) {
            $this->hesabfa->saveWarehouseReceipt($invoiceNumber);
        }

        return [
            'success' => true,
            'invoice_number' => $invoiceNumber,
            'reference' => $reference,
        ];
    }

    private function findHesabfaItemCode(string $sku): ?string
    {
        if (empty($sku)) {
            return null;
        }

        $item = $this->hesabfa->findItemBySku($sku);

        Log::channel('hesabfa')->debug('Hesabfa findItemBySku result', [
            'sku' => $sku,
            'item' => $item,
            'item_code' => $item['Code'] ?? $item['ItemCode'] ?? null,
        ]);

        return $item['Code'] ?? $item['ItemCode'] ?? null;
    }

    private function extractNationalCode(Order $order): ?string
    {
        $code = $order->user?->national_code ?? $order->address?->receiver_national_code ?? null;

        if (! $code) {
            return null;
        }

        $code = $this->normalizeNationalCode($code);

        return strlen($code) === 10 ? $code : null;
    }

    private function normalizeNationalCode(string $code): string
    {
        $persianDigits = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $arabicDigits = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];

        $code = str_replace($persianDigits, range(0, 9), $code);
        $code = str_replace($arabicDigits, range(0, 9), $code);

        return preg_replace('/\D/', '', $code);
    }

    private function normalizeMobile(?string $phone): string
    {
        if (! $phone) {
            return '';
        }

        $phone = $this->normalizeNationalCode($phone);

        if (str_starts_with($phone, '0098')) {
            $phone = '0'.substr($phone, 4);
        } elseif (str_starts_with($phone, '98')) {
            $phone = '0'.substr($phone, 2);
        }

        if (strlen($phone) === 10 && str_starts_with($phone, '0')) {
            return $phone;
        }

        return $phone;
    }

    private function log(Order $order, string $type, string $status, ?array $data = null, ?string $error = null): void
    {
        HesabfaSyncLog::create([
            'order_id' => $order->id,
            'sync_type' => $type,
            'status' => $status,
            'response_data' => $data,
            'error_message' => $error,
        ]);
    }
}
