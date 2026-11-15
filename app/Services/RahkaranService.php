<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class RahkaranService
{
    public function __construct(private RahkaranSettings $settings) {}

    public function isConfigured(): bool
    {
        return filter_var($this->settings->get('base_url'), FILTER_VALIDATE_URL) !== false
            && filled($this->settings->get('username'))
            && filled($this->settings->get('password'));
    }

    public function getSerialsForPart(int $partRef): array
    {
        $response = $this->get($this->url("/Logistics/InventorySerialManagement/WebServices/LicenseBasedServices/InventorySerialManagement.svc/SerialsInfo/{$partRef}"));

        $data = $this->decode($response->throw()->json());

        return is_array($data) ? $data : [];
    }

    public function getPartBySerial(string $serial): ?array
    {
        $response = $this->get($this->url('/Logistics/InventorySerialManagement/WebServices/LicenseBasedServices/InventorySerialManagement.svc/PartBySerial/'.rawurlencode($serial)));
        $data = $this->decode($response->throw()->json());

        if (isset($data[0]) && is_array($data[0])) {
            return $data[0];
        }

        return is_array($data) ? $data : null;
    }

    public function getStock(int $partRef, int $inventoryId, int $plantId): float
    {
        $response = $this->post($this->url('/Sales/SalesDocument/Services/SalesDocumentService.svc/productInventoryRemainingValue'), [
            'ProductID' => $partRef,
            'InventoryID' => $inventoryId,
            'PlantID' => $plantId,
        ]);
        $data = $this->decode($response->throw()->json());

        if (! is_array($data)) {
            return 0;
        }

        return (float) ($data['ActualRemainingValue'] ?? $data['actualRemainingValue'] ?? $data[0]['ActualRemainingValue'] ?? $data[0]['actualRemainingValue'] ?? 0);
    }

    public function findCustomerIdByNationalId(string $nationalId): ?int
    {
        $result = $this->callBusinessRule('وب سرویس فراخوانی شناسه مشتری با کد ملی', ['NationalID' => $nationalId]);
        $customerId = $result['CustomerID'] ?? $result[0]['CustomerID'] ?? null;

        return $customerId ? (int) $customerId : null;
    }

    public function createCustomerAddress(int $customerId, array $address): ?int
    {
        $result = $this->callBusinessRule('ایجاد آدرس جدید برای مشتری', [
            'CustomerID' => $customerId,
            'RegionalDivisionRef' => $address['regional_division_ref'],
            'Details' => $address['details'],
            'Phone' => $address['phone'],
            'ZipCode' => $address['zip_code'],
            'AddressName' => $address['name'],
            'Type' => 1,
        ]);
        $addressId = $result['CustomerAddressID'] ?? null;

        return $addressId ? (int) $addressId : null;
    }

    public function placeInvoice(array $invoice): int
    {
        $response = $this->post($this->url('/Sales/InvoiceManagement/Services/InvoiceManagementService.svc/PlaceInvoice'), $invoice);
        $data = $this->decode($response->throw()->json());
        $invoiceId = is_numeric($data)
            ? $data
            : ($data['ID'] ?? $data[0]['ID'] ?? null);

        if (! $invoiceId) {
            throw new RuntimeException('راهکاران شناسه فاکتور را برنگرداند.');
        }

        return (int) $invoiceId;
    }

    public function addInvoiceTrackingFactors(int $invoiceId, array $factors): void
    {
        $this->callBusinessRule('اضافه کردن عامل کنترل موجودی در فاکتور فروش', [
            'InvoiceID' => $invoiceId,
            'InEtelatTakmiliFaktorItemList' => $factors,
        ]);
    }

    public function testConnection(): array
    {
        $response = $this->get($this->url('/Framework/Services/AuthenticationService.svc/now'));

        $response->throw();

        return ['success' => true, 'server_time' => $response->json()];
    }

    private function callBusinessRule(string $name, array $parameters): array
    {
        $response = $this->post($this->url('/System/BusinessRuleEngine/Service.svc/Call'), [
            'Name' => $name,
            'Parameters' => json_encode($parameters, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        ]);

        $result = $this->decode($response->throw()->json());
        if (is_string($result)) {
            return json_decode($result, true, flags: JSON_THROW_ON_ERROR);
        }

        return is_array($result) ? $result : [];
    }

    private function request(): PendingRequest
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('تنظیمات اتصال راهکاران کامل نیست.');
        }

        return Http::timeout(30)
            ->acceptJson()
            ->withCookies(['sg-auth' => $this->sessionCookie()], (string) parse_url((string) $this->settings->get('base_url'), PHP_URL_HOST));
    }

    private function get(string $url): Response
    {
        $response = $this->request()->get($url);
        if (in_array($response->status(), [401, 403], true)) {
            Cache::forget('rahkaran:session-cookie');
            $response = $this->request()->get($url);
        }

        return $response;
    }

    private function post(string $url, array $payload): Response
    {
        $response = $this->request()->post($url, $payload);
        if (in_array($response->status(), [401, 403], true)) {
            Cache::forget('rahkaran:session-cookie');
            $response = $this->request()->post($url, $payload);
        }

        return $response;
    }

    private function sessionCookie(): string
    {
        return Cache::remember('rahkaran:session-cookie', now()->addMinutes(20), function (): string {
            $baseUrl = rtrim((string) $this->settings->get('base_url'), '/');
            $session = Http::timeout(30)->acceptJson()->get($baseUrl.'/Framework/Services/AuthenticationService.svc/session')->throw()->json();
            $encryptedPassword = $this->encryptPassword(
                (string) $session['id'],
                (string) $session['rsa']['M'],
                (string) $session['rsa']['E'],
            );
            $login = Http::timeout(30)->acceptJson()->post($baseUrl.'/Framework/Services/AuthenticationService.svc/login', [
                'sessionId' => $session['id'],
                'username' => $this->settings->get('username'),
                'password' => $encryptedPassword,
            ])->throw();
            $cookie = $login->cookies()->getCookieByName('sg-auth')?->getValue();

            if (! $cookie) {
                throw new RuntimeException('ورود به راهکاران انجام شد اما کوکی نشست دریافت نشد.');
            }

            return $cookie;
        });
    }

    private function encryptPassword(string $sessionId, string $modulus, string $exponent): string
    {
        $rsaPublicKey = $this->derSequence(
            $this->derInteger($this->decodeHex($modulus))
                .$this->derInteger($this->decodeHex($exponent)),
        );
        $rsaAlgorithm = hex2bin('300d06092a864886f70d0101010500');
        $subjectPublicKey = "\x03".$this->derLength(strlen($rsaPublicKey) + 1)."\x00".$rsaPublicKey;
        $subjectPublicKeyInfo = $this->derSequence($rsaAlgorithm.$subjectPublicKey);
        $rsaKey = "-----BEGIN PUBLIC KEY-----\n".chunk_split(base64_encode($subjectPublicKeyInfo), 64, "\n")."-----END PUBLIC KEY-----\n";
        $publicKey = openssl_pkey_get_public($rsaKey);
        if ($publicKey === false) {
            throw new RuntimeException('کلید عمومی RSA دریافتی از راهکاران معتبر نیست.');
        }

        if (! openssl_public_encrypt($sessionId.'**'.$this->settings->get('password'), $encrypted, $publicKey, OPENSSL_PKCS1_PADDING)) {
            throw new RuntimeException('رمزنگاری گذرواژه راهکاران انجام نشد.');
        }

        return strtoupper(bin2hex($encrypted));
    }

    private function decodeHex(string $value): string
    {
        $hex = preg_replace('/\s+/', '', trim($value)) ?? '';
        $hex = preg_replace('/^0x/i', '', $hex) ?? '';
        if ($hex === '' || ! ctype_xdigit($hex)) {
            throw new RuntimeException('پارامترهای کلید عمومی RSA راهکاران قالب هگز معتبر ندارند.');
        }

        if (strlen($hex) % 2 !== 0) {
            $hex = '0'.$hex;
        }

        $bytes = hex2bin($hex);
        if ($bytes === false) {
            throw new RuntimeException('تبدیل پارامترهای کلید عمومی RSA راهکاران انجام نشد.');
        }

        return $bytes;
    }

    private function derSequence(string $value): string
    {
        return "\x30".$this->derLength(strlen($value)).$value;
    }

    private function derInteger(string $value): string
    {
        $value = ltrim($value, "\x00");
        if ($value !== '' && (ord($value[0]) & 0x80)) {
            $value = "\x00".$value;
        }

        return "\x02".$this->derLength(strlen($value)).$value;
    }

    private function derLength(int $length): string
    {
        if ($length < 128) {
            return chr($length);
        }

        $bytes = ltrim(pack('N', $length), "\x00");

        return chr(0x80 | strlen($bytes)).$bytes;
    }

    private function decode(mixed $data): mixed
    {
        if (is_string($data)) {
            $decoded = json_decode($data, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }

        return is_array($data) ? $data : [];
    }

    private function url(string $path): string
    {
        return rtrim((string) $this->settings->get('base_url'), '/').$path;
    }
}
