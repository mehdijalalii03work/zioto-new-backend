<?php

namespace App\Payment\Drivers\Smartis;

use GuzzleHttp\Client;
use Shetabit\Multipay\Abstracts\Driver;
use Shetabit\Multipay\Contracts\ReceiptInterface;
use Shetabit\Multipay\Exceptions\InvalidPaymentException;
use Shetabit\Multipay\Exceptions\PurchaseFailedException;
use Shetabit\Multipay\Invoice;
use Shetabit\Multipay\Receipt;
use Shetabit\Multipay\RedirectionForm;
use Shetabit\Multipay\Request;

class Smartis extends Driver
{
    protected Client $client;

    protected ?string $accessToken = null;

    public function __construct(Invoice $invoice, $settings)
    {
        $this->invoice($invoice);
        $this->settings = (object) $settings;
        $this->client = new Client;
    }

    public function purchase(): string
    {
        $this->authenticate();

        $phone = $this->invoice->getDetails()['phone'] ?? '';
        $referenceId = $this->invoice->getDetails()['referenceId'] ?? str_replace('-', '', (string) $this->invoice->getUuid());
        $useIpg = ($this->settings->useIpg ?? true) ? 'true' : 'false';
        $callbackUrl = $this->settings->callbackUrl;
        $terminalId = $this->settings->terminalId;
        $amount = $this->invoice->getAmount();

        $hashInput = "{$phone}:{$callbackUrl}:{$useIpg}:{$terminalId}:{$amount}:{$referenceId}";
        $xHash = hash_hmac('sha256', $hashInput, $this->settings->secretKey);

        $payload = [
            'username' => $phone,
            'callback' => $callbackUrl,
            'useIpg' => $useIpg,
            'terminalId' => $terminalId,
            'amount' => $amount,
            'referenceId' => $referenceId,
        ];

        $response = $this->client->request('POST', $this->settings->apiCreatePaymentUrl, [
            'json' => $payload,
            'headers' => [
                'Authorization' => 'Bearer '.$this->accessToken,
                'x-hash' => $xHash,
                'accept' => 'application/json',
            ],
            'http_errors' => false,
            'verify' => false,
        ]);

        $body = json_decode($response->getBody()->getContents(), true);

        if (($body['status'] ?? '') !== 'SUCCESS') {
            $message = $body['message'] ?? 'خطا در ایجاد درخواست پرداخت';
            throw new PurchaseFailedException($message);
        }

        $uuid = $body['target']['uuid'] ?? '';
        $this->invoice->transactionId($uuid);

        return $this->invoice->getTransactionId();
    }

    public function pay(): RedirectionForm
    {
        $uuid = $this->invoice->getTransactionId();
        $payUrl = $this->settings->paymentPageUrl.'?uuid='.$uuid;

        return $this->redirectWithForm($payUrl, [], 'GET');
    }

    public function verify(): ReceiptInterface
    {
        $this->authenticate();

        $uuid = $this->invoice->getTransactionId() ?? Request::input('uuid');

        $response = $this->client->request('GET', $this->settings->apiStatusPaymentUrl.'?uuid='.$uuid, [
            'headers' => [
                'Authorization' => 'Bearer '.$this->accessToken,
                'accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
            'http_errors' => false,
            'verify' => false,
        ]);

        $body = json_decode($response->getBody()->getContents(), true);

        if (($body['status'] ?? '') !== 'SUCCESS') {
            throw new InvalidPaymentException('خطا در بررسی وضعیت پرداخت');
        }

        $target = $body['target'] ?? [];

        if (($target['status'] ?? '') !== 'TRUE') {
            throw new InvalidPaymentException('پرداخت تایید نشد');
        }

        $orderAmount = $this->invoice->getAmount();
        $paidAmount = $target['amount'] ?? 0;

        if ((string) $paidAmount !== (string) $orderAmount) {
            throw new InvalidPaymentException('مبلغ پرداخت شده با مبلغ سفارش مطابقت ندارد');
        }

        $this->confirmPayment($uuid);

        $traceId = $target['traceId'] ?? '';

        return $this->createReceipt($traceId, [
            'uuid' => $uuid,
            'amount' => $paidAmount,
        ]);
    }

    protected function authenticate(): void
    {
        if ($this->accessToken !== null) {
            return;
        }

        $response = $this->client->request('POST', $this->settings->apiAuthTokenUrl, [
            'json' => [
                'username' => $this->settings->username,
                'password' => $this->settings->password,
            ],
            'headers' => [
                'accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
            'http_errors' => false,
            'verify' => false,
        ]);

        $body = json_decode($response->getBody()->getContents(), true);

        if (($body['status'] ?? '') !== 'SUCCESS') {
            throw new PurchaseFailedException('خطا در احراز هویت با درگاه اسمارتیز');
        }

        $this->accessToken = $body['target']['accessToken'] ?? '';
    }

    protected function confirmPayment(string $uuid): void
    {
        $this->client->request('GET', $this->settings->apiVerifyPaymentUrl.'?uuid='.$uuid, [
            'headers' => [
                'Authorization' => 'Bearer '.$this->accessToken,
            ],
            'http_errors' => false,
            'verify' => false,
        ]);
    }

    protected function createReceipt(string $referenceId, array $detail = []): Receipt
    {
        $receipt = new Receipt('smartis', $referenceId);
        $receipt->detail($detail);

        return $receipt;
    }
}
