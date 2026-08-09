<?php

namespace App\Payment\Drivers;

use GuzzleHttp\RequestOptions;
use Shetabit\Multipay\Contracts\ReceiptInterface;
use Shetabit\Multipay\Drivers\Digipay\Digipay;
use Shetabit\Multipay\Exceptions\InvalidPaymentException;
use Shetabit\Multipay\Exceptions\PurchaseFailedException;
use Shetabit\Multipay\Receipt;
use Shetabit\Multipay\Request;

class CustomDigipay extends Digipay
{
    const VERIFY_URL_V2 = '/digipay/api/purchases/verify';

    private const MAX_VERIFY_RETRIES = 3;

    private int $verifyRetries = 0;

    private int $verifyRetryDelay = 5;

    protected function oauth()
    {
        $response = $this
            ->client
            ->request(
                'POST',
                $this->settings->apiPaymentUrl.self::OAUTH_URL,
                [
                    RequestOptions::HEADERS => [
                        'Authorization' => 'Basic '.base64_encode("{$this->settings->client_id}:{$this->settings->client_secret}"),
                    ],
                    RequestOptions::FORM_PARAMS => [
                        'username' => $this->settings->username,
                        'password' => $this->settings->password,
                        'grant_type' => 'password',
                    ],
                    RequestOptions::HTTP_ERRORS => false,
                ]
            );

        if ($response->getStatusCode() != 200) {
            if ($response->getStatusCode() == 401) {
                throw new PurchaseFailedException('خطا نام کاربری یا رمز عبور شما اشتباه می‌باشد.');
            }
            throw new PurchaseFailedException('خطا در هنگام احراز هویت.');
        }

        $body = json_decode($response->getBody()->getContents(), true);

        $this->oauthToken = $body['access_token'];

        return $body['access_token'];
    }

    /**
     * Verify the purchase using the body-based contract the official Digipay
     * plugin has used since v1.6.8: providerId + trackingCode in the JSON body
     * and the ticket type as a query parameter.
     *
     * @throws InvalidPaymentException
     */
    public function verify(): ReceiptInterface
    {
        $digipayTicketType = Request::input('type');
        $trackingCode = Request::input('trackingCode');
        $providerId = Request::input('providerId') ?? $this->invoice->getUuid();

        $response = $this->client->request(
            'POST',
            $this->settings->apiPaymentUrl.self::VERIFY_URL_V2,
            [
                RequestOptions::QUERY => ['type' => $digipayTicketType],
                RequestOptions::BODY => json_encode([
                    'trackingCode' => $trackingCode,
                    'providerId' => $providerId,
                ]),
                RequestOptions::HEADERS => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer '.$this->oauthToken,
                ],
                RequestOptions::HTTP_ERRORS => false,
            ]
        );

        $body = json_decode($response->getBody()->getContents(), true);
        $status = $body['result']['status'] ?? null;

        if ($response->getStatusCode() != 200) {
            $message = $body['result']['message'] ?? 'تراکنش تایید نشد';
            throw new InvalidPaymentException($message, (int) $response->getStatusCode());
        }

        // Pending status → retry a few times before giving up.
        if ($status === 9011 && $this->verifyRetries < self::MAX_VERIFY_RETRIES) {
            $this->verifyRetries++;
            sleep($this->verifyRetryDelay);

            return $this->verify();
        }

        if ($status !== 0) {
            $message = $body['result']['message'] ?? 'تراکنش تایید نشد';
            throw new InvalidPaymentException($message, (int) $response->getStatusCode());
        }

        return (new Receipt('digipay', $body['trackingCode'] ?? $trackingCode))->detail($body);
    }
}
