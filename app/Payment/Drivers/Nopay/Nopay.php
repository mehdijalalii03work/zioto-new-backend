<?php

namespace App\Payment\Drivers\Nopay;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Shetabit\Multipay\Abstracts\Driver;
use Shetabit\Multipay\Contracts\ReceiptInterface;
use Shetabit\Multipay\Exceptions\InvalidPaymentException;
use Shetabit\Multipay\Exceptions\PurchaseFailedException;
use Shetabit\Multipay\Invoice;
use Shetabit\Multipay\Receipt;
use Shetabit\Multipay\RedirectionForm;
use Shetabit\Multipay\Request;

class Nopay extends Driver
{
    protected Client $client;

    /**
     * @var (callable(string, string, array): void)|null
     */
    protected $logger = null;

    public function __construct(Invoice $invoice, $settings)
    {
        $this->invoice($invoice);
        $this->settings = (object) $settings;
        $this->client = new Client([
            'verify' => (bool) ($this->settings->verifySsl ?? false),
        ]);
    }

    /**
     * Allow tests to inject a no-op logger (Laravel's container is not booted
     * in plain unit tests, so the `log` service is unavailable there).
     */
    public function setLogger(callable $logger): void
    {
        $this->logger = $logger;
    }

    private function log(string $level, string $message, array $context = []): void
    {
        $logger = $this->logger;

        if ($logger === null) {
            try {
                $channel = Log::channel('payment');
            } catch (\Throwable) {
                return; // container not booted (unit tests) — skip logging
            }
            $channel->{$level}($message, $context);

            return;
        }

        $logger($level, $message, $context);
    }

    public function purchase(): string
    {
        $encryptedPassword = $this->encryptPassword($this->settings->password);

        // The return URL must match EXACTLY what the merchant panel has
        // registered (error 282 otherwise). The registered URL1 (Silent
        // Response) is the bare callback endpoint — no orderId/gateway suffix.
        $returnUrl = ! empty($this->settings->callbackUrl)
            ? $this->settings->callbackUrl
            : ($this->settings->nopayCallbackUrl ?? '');

        $payload = [
            'serviceUserName' => $this->settings->username,
            'servicePassword' => $encryptedPassword,
            'cellNumber' => $this->settings->cellNumber,
            'amount' => (int) $this->invoice->getAmount(),
            'returnURL' => $returnUrl,
            'merchantNumber' => $this->settings->merchantNumber,
        ];

        $this->log('info', 'Nopay purchase request', [
            'url' => $this->settings->apiBaseUrl.'/CPG/Security/Token/RequestToken',
            'amount' => $this->invoice->getAmount(),
        ]);

        $body = $this->apiRequest('/CPG/Security/Token/RequestToken', $payload, 'CPG/Security/Token/RequestToken');

        $result = $this->arrayGet($body, 'Result');
        $entity = $this->arrayGet($result, 'entity');

        if (! $entity) {
            throw new PurchaseFailedException('پاسخ نامعتبر از درگاه nopay');
        }

        $token = $entity['token'] ?? $entity['Token'] ?? null;
        $redirectUrl = $entity['redirectURL'] ?? $entity['RedirectURL'] ?? null;

        if (! $token) {
            throw new PurchaseFailedException('توکن پرداخت دریافت نشد');
        }

        $this->invoice->transactionId($token);
        $this->invoice->detail([
            'redirectURL' => $redirectUrl,
        ]);

        $this->log('info', 'Nopay purchase success', [
            'token' => $token,
            'redirectURL' => $redirectUrl,
        ]);

        return $this->invoice->getTransactionId();
    }

    public function pay(): RedirectionForm
    {
        $token = $this->invoice->getTransactionId();
        $redirectUrl = $this->invoice->getDetails()['redirectURL'] ?? null;

        if (! $redirectUrl) {
            throw new PurchaseFailedException('آدرس بازگشت دریافت نشد');
        }

        $payUrl = $redirectUrl.'?token='.$token;

        return $this->redirectWithForm($payUrl, [], 'GET');
    }

    public function verify(): ReceiptInterface
    {
        $token = $this->invoice->getTransactionId()
            ?? Request::input('Token')
            ?? Request::input('token');

        $encryptedPassword = $this->encryptPassword($this->settings->password);

        $payload = [
            'serviceUserName' => $this->settings->username,
            'servicePassword' => $encryptedPassword,
            'token' => $token,
        ];

        $this->log('info', 'Nopay verify request', [
            'token' => $token,
        ]);

        $body = $this->apiRequest('/BNPL/Financial/CPG/VerifyTransaction', $payload, 'BNPL/Financial/CPG/VerifyTransaction');

        $result = $this->arrayGet($body, 'Result');
        $entity = $this->arrayGet($result, 'entity');

        if (! $entity) {
            throw new InvalidPaymentException('پاسخ نامعتبر از درگاه nopay');
        }

        $isApproved = (bool) ($entity['IsApproved'] ?? $entity['isApproved'] ?? false);

        if (! $isApproved) {
            throw new InvalidPaymentException('پرداخت تایید نشد');
        }

        $refNumber = (string) ($entity['RefNumber'] ?? $entity['refNumber'] ?? '');
        $orderId = (string) ($entity['OrderID'] ?? $entity['orderId'] ?? '');

        $this->log('info', 'Nopay verify success', [
            'refNumber' => $refNumber,
            'orderId' => $orderId,
        ]);

        return $this->createReceipt($refNumber, [
            'order_id' => $orderId,
            'is_approved' => $isApproved,
        ]);
    }

    private function encryptPassword(string $password): string
    {
        $key = base64_decode($this->settings->privateKey);
        $iv = base64_decode($this->settings->publicKey);
        $encrypted = openssl_encrypt($password, 'AES-128-CBC', $key, OPENSSL_RAW_DATA, $iv);

        return base64_encode($encrypted);
    }

    private function apiRequest(string $path, array $payload, ?string $serviceName = null): array
    {
        $format = $this->settings->requestFormat ?? 'flat';

        // The gateway's sample curl wraps the payload in InputValue/ServiceName
        // and posts to the BASE URL only (no path suffix). The function name
        // lives inside the body as ServiceName.
        // The PDF docs instead append the path to the URL with a flat body.
        $isWrapper = $format === 'wrapper' && $serviceName;
        $url = $isWrapper
            ? rtrim($this->settings->apiBaseUrl, '/')
            : rtrim($this->settings->apiBaseUrl, '/').'/'.ltrim($path, '/');

        $requestBody = $isWrapper
            ? ['InputValue' => $payload, 'ServiceName' => $serviceName]
            : $payload;

        $response = $this->client->request('POST', $url, [
            'json' => $requestBody,
            'headers' => [
                'accept' => '*/*',
                'Content-Type' => 'application/json',
            ],
            'http_errors' => false,
        ]);

        $body = json_decode($response->getBody()->getContents(), true) ?? [];

        $this->log('debug', 'Nopay API response', [
            'path' => $path,
            'status_code' => $response->getStatusCode(),
            'body' => $body,
        ]);

        $notification = $this->arrayGet($body, 'Notification');
        $hasErrors = (bool) $this->arrayGet($notification, 'HasErrors', false);

        if ($hasErrors) {
            $errors = $this->arrayGet($notification, 'Errors', []);
            $firstError = $errors[0] ?? [];
            $errorMessage = $firstError['message'] ?? 'خطای ناشناخته';
            $errorCode = $firstError['code'] ?? null;

            throw new PurchaseFailedException("nopay error [{$errorCode}]: {$errorMessage}");
        }

        return $body;
    }

    /**
     * Case-insensitive lookup helper for gateway responses whose JSON key casing
     * is not guaranteed (e.g. Result/result, Notification/notification).
     */
    private function arrayGet(mixed $data, string $key, mixed $default = null): mixed
    {
        if (! is_array($data)) {
            return $default;
        }

        if (array_key_exists($key, $data)) {
            return $data[$key];
        }

        $lower = strtolower($key);

        foreach ($data as $k => $value) {
            if (strtolower((string) $k) === $lower) {
                return $value;
            }
        }

        return $default;
    }

    protected function createReceipt(string $referenceId, array $detail = []): Receipt
    {
        $receipt = new Receipt('nopay', $referenceId);
        $receipt->detail($detail);

        return $receipt;
    }
}
