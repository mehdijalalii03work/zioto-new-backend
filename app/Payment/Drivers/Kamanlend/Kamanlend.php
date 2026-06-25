<?php

namespace App\Payment\Drivers\Kamanlend;

use GuzzleHttp\Client;
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

        $response = $this->client->request('POST', $this->settings->apiRegisterPaymentUrl, [
            'json' => $payload,
            'http_errors' => false,
            'verify' => false,
        ]);

        $body = json_decode($response->getBody()->getContents(), true);

        if (empty($body['success'])) {
            $message = $body['messages'][0]['message'] ?? 'خطا در ثبت درخواست پرداخت';
            throw new PurchaseFailedException($message);
        }

        $this->invoice->transactionId($body['result']['token']);

        return $this->invoice->getTransactionId();
    }

    public function pay(): RedirectionForm
    {
        $token = $this->invoice->getTransactionId();

        $payUrl = $this->settings->gatewayUrl.'?token='.$token;

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
            'verify' => false,
        ]);

        $body = json_decode($response->getBody()->getContents(), true);

        if (empty($body['success'])) {
            $message = $body['messages'][0]['message'] ?? 'پرداخت تایید نشد';
            throw new InvalidPaymentException($message);
        }

        $state = $body['result']['saleRequestState'] ?? '';

        if ($state !== 'PaymentCompleted') {
            throw new InvalidPaymentException('وضعیت پرداخت: '.($body['result']['saleRequestStateTitle'] ?? $state));
        }

        return $this->createReceipt($token, [
            'state' => $state,
            'stateTitle' => $body['result']['saleRequestStateTitle'] ?? '',
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
