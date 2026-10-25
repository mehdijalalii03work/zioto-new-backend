<?php

namespace App\Payment\Drivers;

use App\Exceptions\DigipayVerificationPendingException;
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

    private const DEFINITIVE_FAILURE_STATUSES = [9007];

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
                    RequestOptions::CONNECT_TIMEOUT => 3,
                    RequestOptions::TIMEOUT => 8,
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
        return $this->verifyWithContext([
            'type' => Request::input('type'),
            'trackingCode' => Request::input('trackingCode'),
            'providerId' => Request::input('providerId') ?? $this->invoice->getUuid(),
        ]);
    }

    /**
     * @param  array{type: string|int, trackingCode: string, providerId: string}  $context
     *
     * @throws InvalidPaymentException
     * @throws DigipayVerificationPendingException
     */
    public function verifyWithContext(array $context): ReceiptInterface
    {
        $digipayTicketType = $context['type'];
        $trackingCode = $context['trackingCode'];
        $providerId = $context['providerId'];

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
                RequestOptions::CONNECT_TIMEOUT => 3,
                RequestOptions::TIMEOUT => 8,
            ]
        );

        $body = json_decode($response->getBody()->getContents(), true);
        $status = $body['result']['status'] ?? null;

        if ($response->getStatusCode() != 200) {
            $message = $body['result']['message'] ?? 'تراکنش تایید نشد';
            throw new DigipayVerificationPendingException($message, (int) $response->getStatusCode());
        }

        if ($status === 9011 || $status === null) {
            throw new DigipayVerificationPendingException($body['result']['message'] ?? 'نتیجه پرداخت هنوز مشخص نیست', $response->getStatusCode(), is_numeric($status) ? (int) $status : null);
        }

        if ($status !== 0) {
            $message = $body['result']['message'] ?? 'تراکنش تایید نشد';

            if (in_array((int) $status, self::DEFINITIVE_FAILURE_STATUSES, true)) {
                throw new InvalidPaymentException($message, (int) $status);
            }

            throw new DigipayVerificationPendingException($message, $response->getStatusCode(), is_numeric($status) ? (int) $status : null);
        }

        return (new Receipt('digipay', $body['trackingCode'] ?? $trackingCode))->detail($body);
    }
}
