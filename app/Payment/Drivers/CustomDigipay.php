<?php

namespace App\Payment\Drivers;

use GuzzleHttp\RequestOptions;
use Shetabit\Multipay\Drivers\Digipay\Digipay;
use Shetabit\Multipay\Exceptions\PurchaseFailedException;

class CustomDigipay extends Digipay
{
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
}
