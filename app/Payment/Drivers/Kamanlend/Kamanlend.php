<?php

namespace App\Payment\Drivers\Kamanlend;

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

class Kamanlend extends Driver
{
    protected Client $client;

    public function __construct(Invoice $invoice, $settings)
    {
        $this->invoice($invoice);
        $this->settings = (object) $settings;
        $this->client = new Client;
    }

    public function purchase(): string
    {
        $nationalCode = $this->invoice->getDetails()['nationalCode'] ?? null;
        $shoppingCardCode = $this->invoice->getDetails()['shoppingCardCode'] ?? (string) time();
        $redirectionUrl = $this->invoice->getDetails()['redirectionUrl'] ?? $this->settings->callbackUrl;
        $saleItems = $this->invoice->getDetails()['saleItems'] ?? $this->buildDefaultSaleItems();

        $payload = [
            'terminalCode' => $this->settings->terminalCode,
            'terminalSecret' => $this->settings->terminalSecret,
            'customerNationalCode' => $nationalCode,
            'shoppingCardCode' => $shoppingCardCode,
            'stateChangeCallbackUrl' => $this->settings->callbackUrl,
            'redirectionUrl' => $redirectionUrl,
            'saleItems' => $saleItems,
        ];

        Log::channel('payment')->info('Kamanlend purchase request', [
            'url' => $this->settings->apiRegisterPaymentUrl,
            'payload' => $payload,
        ]);

        $response = $this->client->request('POST', $this->settings->apiRegisterPaymentUrl, [
            'json' => $payload,
            'http_errors' => false,
        ]);

        $responseBody = $response->getBody()->getContents();
        $body = array_change_key_case(json_decode($responseBody, true), CASE_LOWER);

        Log::channel('payment')->info('Kamanlend purchase response', [
            'status_code' => $response->getStatusCode(),
            'body' => $body,
        ]);

        if (empty($body['success'])) {
            $message = $body['messages'][0]['message'] ?? 'خطا در ثبت درخواست پرداخت';
            throw new PurchaseFailedException($message);
        }

        Log::channel('payment')->info('Kamanlend purchase success', [
            'result' => $body['result'],
        ]);

        $token = $body['result']['token'] ?? $body['result']['Token'] ?? null;
        $this->invoice->transactionId($token);
        $this->invoice->detail([
            'gatewayUrl' => $body['result']['gatewayurl'] ?? $body['result']['gatewayUrl'] ?? $body['result']['GatewayUrl'] ?? null,
        ]);

        return $this->invoice->getTransactionId();
    }

    public function pay(): RedirectionForm
    {
        $token = $this->invoice->getTransactionId();

        $gatewayUrl = $this->invoice->getDetails()['gatewayUrl'] ?? null;
        $payUrl = $gatewayUrl ?: $this->settings->gatewayUrl.'/Payment?token='.$token;

        return $this->redirectWithForm($payUrl, [], 'GET');
    }

    public function verify(): ReceiptInterface
    {
        $token = $this->invoice->getTransactionId() ?? Request::input('token');
        $nationalCode = $this->invoice->getDetails()['nationalCode'] ?? Request::input('nationalCode');

        $payload = [
            'terminalCode' => $this->settings->terminalCode,
            'terminalSecret' => $this->settings->terminalSecret,
            'customerNationalCode' => $nationalCode,
            'token' => $token,
        ];

        $response = $this->client->request('POST', $this->settings->apiGetPaymentStateUrl, [
            'json' => $payload,
            'http_errors' => false,
        ]);

        $body = array_change_key_case(json_decode($response->getBody()->getContents(), true), CASE_LOWER);

        if (empty($body['success'])) {
            $message = $body['messages'][0]['message'] ?? 'پرداخت تایید نشد';
            throw new InvalidPaymentException($message);
        }

        $state = $body['result']['salerequeststate'] ?? '';

        if ($state !== 'PaymentCompleted') {
            throw new InvalidPaymentException('وضعیت پرداخت: '.($body['result']['salerequeststatetitle'] ?? $state));
        }

        return $this->createReceipt($token, [
            'state' => $state,
            'stateTitle' => $body['result']['salerequeststatetitle'] ?? '',
        ]);
    }

    protected function buildDefaultSaleItems(): array
    {
        $amount = $this->invoice->getAmount();
        $description = $this->invoice->getDetails()['description'] ?? 'پرداخت';

        return [
            [
                'code' => '0',
                'title' => $description,
                'quantity' => 1,
                'totalAmountRial' => $amount,
            ],
        ];
    }

    protected function createReceipt(string $referenceId, array $detail = []): Receipt
    {
        $receipt = new Receipt('kamanlend', $referenceId);
        $receipt->detail($detail);

        return $receipt;
    }
}
