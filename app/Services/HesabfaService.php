<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class HesabfaService
{
    private string $apiKey;

    private string $loginToken;

    private string $baseUrl;

    public function __construct()
    {
        $this->apiKey = config('hesabfa.api_key');
        $this->loginToken = config('hesabfa.login_token');
        $this->baseUrl = config('hesabfa.base_url', 'https://api.hesabfa.com/v1');
    }

    public function isConfigured(): bool
    {
        return ! empty($this->apiKey) && ! empty($this->loginToken);
    }

    public function testConnection(): array
    {
        $result = $this->call('/contact/getcontacts', [
            'queryInfo' => [
                'take' => 1,
                'skip' => 0,
                'filters' => [],
            ],
        ]);

        if ($result['success']) {
            return ['success' => true, 'message' => 'اتصال به حسابفا برقرار است'];
        }

        return ['success' => false, 'message' => $result['error'] ?? 'خطا در اتصال به حسابفا'];
    }

    // ── Contact Methods ──────────────────────────────────────────────

    public function findContactByNationalCode(string $nationalCode): ?array
    {
        $result = $this->call('/contact/getcontacts', [
            'queryInfo' => [
                'take' => 1,
                'skip' => 0,
                'filters' => [
                    [
                        'property' => 'NationalCode',
                        'operator' => '=',
                        'value' => $nationalCode,
                    ],
                ],
            ],
        ]);

        if ($result['success'] && ! empty($result['data']['Result']['items'])) {
            return $result['data']['Result']['items'][0];
        }

        return null;
    }

    public function saveContact(array $contactData): array
    {
        return $this->call('/contact/save', ['contact' => $contactData]);
    }

    // ── Item Methods ─────────────────────────────────────────────────

    public function getItemByCode(string $itemCode): ?array
    {
        $result = $this->call('/item/get', ['ItemCode' => $itemCode]);

        if ($result['success'] && ! empty($result['data']['Result'])) {
            return $result['data']['Result'];
        }

        return null;
    }

    public function findItemBySku(string $sku): ?array
    {
        $result = $this->call('/item/getitems', [
            'queryInfo' => [
                'take' => 1,
                'skip' => 0,
                'filters' => [
                    [
                        'property' => 'ProductCode',
                        'operator' => '=',
                        'value' => $sku,
                    ],
                ],
            ],
        ]);

        if ($result['success'] && ! empty($result['data']['Result']['items'])) {
            return $result['data']['Result']['items'][0];
        }

        return null;
    }

    public function getAllItemQuantities(?string $warehouseCode = null): array
    {
        $payload = [];
        if ($warehouseCode) {
            $payload['warehouseCode'] = $warehouseCode;
        }

        $result = $this->call('/item/GetQuantity', $payload);

        if ($result['success'] && ! empty($result['data']['Result'])) {
            return $result['data']['Result'];
        }

        return [];
    }

    // ── Invoice Methods ──────────────────────────────────────────────

    public function saveInvoice(array $invoiceData): array
    {
        return $this->call('/invoice/save', ['invoice' => $invoiceData]);
    }

    public function saveWarehouseReceipt(int $invoiceNumber): array
    {
        return $this->call('/invoice/SaveWarehouseReceipt', [
            'invoiceNumber' => $invoiceNumber,
        ]);
    }

    public function findInvoiceByReference(string $reference): ?array
    {
        $result = $this->call('/invoice/getinvoices', [
            'queryInfo' => [
                'take' => 1,
                'skip' => 0,
                'filters' => [
                    [
                        'property' => 'Reference',
                        'operator' => '=',
                        'value' => $reference,
                    ],
                ],
            ],
        ]);

        if ($result['success'] && ! empty($result['data']['Result']['items'])) {
            return $result['data']['Result']['items'][0];
        }

        return null;
    }

    public function getConfirmedInvoices(string $date): array
    {
        $result = $this->call('/invoice/getinvoices', [
            'queryInfo' => [
                'take' => 500,
                'skip' => 0,
                'filters' => [
                    [
                        'property' => 'Status',
                        'operator' => '=',
                        'value' => 1,
                    ],
                    [
                        'property' => 'Date',
                        'operator' => '=',
                        'value' => $date,
                    ],
                ],
            ],
        ]);

        if ($result['success'] && ! empty($result['data']['Result']['items'])) {
            return $result['data']['Result']['items'];
        }

        return [];
    }

    public function getInvoiceDetails(int $invoiceNumber): ?array
    {
        $result = $this->call('/invoice/get', ['InvoiceNumber' => $invoiceNumber]);

        if ($result['success'] && ! empty($result['data']['Result'])) {
            return $result['data']['Result'];
        }

        return null;
    }

    // ── Core HTTP Method ─────────────────────────────────────────────

    private function call(string $endpoint, array $payload, int $maxRetries = 3): array
    {
        if (! $this->isConfigured()) {
            return ['success' => false, 'error' => 'تنظیمات حسابفا یافت نشد'];
        }

        $payload['apiKey'] = $this->apiKey;
        $payload['loginToken'] = $this->loginToken;

        if (config('app.debug')) {
            $sanitized = $payload;
            unset($sanitized['apiKey'], $sanitized['loginToken']);
            Log::channel('hesabfa')->debug('Hesabfa API request', [
                'endpoint' => $endpoint,
                'payload' => $sanitized,
            ]);
        }

        $lastError = null;

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                $response = Http::timeout(30)
                    ->withHeaders([
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                    ])
                    ->withBody(
                        json_encode($payload, JSON_UNESCAPED_UNICODE),
                        'application/json'
                    )
                    ->post("{$this->baseUrl}{$endpoint}");

                if ($response->successful()) {
                    $data = $response->json();

                    if (config('app.debug')) {
                        Log::channel('hesabfa')->debug('Hesabfa API response', [
                            'endpoint' => $endpoint,
                            'status' => $response->status(),
                            'success' => $data['Success'] ?? false,
                        ]);
                    }

                    if (! empty($data['Success'])) {
                        return ['success' => true, 'data' => $data];
                    }

                    Log::channel('hesabfa')->error('Hesabfa API business error', [
                        'endpoint' => $endpoint,
                        'response' => $data,
                    ]);

                    return ['success' => false, 'error' => $data['ErrorMessage'] ?? 'خطای ناشناخته از حسابفا'];
                }

                if ($response->status() === 429 || $response->status() >= 500) {
                    $lastError = 'خطای سرور حسابفا ('.$response->status().')';

                    if ($attempt < $maxRetries) {
                        sleep($attempt * 2);

                        continue;
                    }
                }

                Log::channel('hesabfa')->error('Hesabfa API HTTP error', [
                    'endpoint' => $endpoint,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return ['success' => false, 'error' => 'خطای سرور حسابفا ('.$response->status().')'];
            } catch (\Exception $e) {
                $lastError = 'خطا در ارتباط با حسابفا: '.$e->getMessage();

                if ($attempt < $maxRetries) {
                    sleep($attempt * 2);

                    continue;
                }
            }
        }

        Log::channel('hesabfa')->error('Hesabfa API failed after retries', [
            'endpoint' => $endpoint,
            'attempts' => $maxRetries,
            'error' => $lastError,
        ]);

        return ['success' => false, 'error' => $lastError ?? 'خطا در ارتباط با حسابفا'];
    }
}
