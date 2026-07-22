<?php

use App\Payment\Drivers\CustomDigipay;
use App\Payment\Drivers\Kamanlend\Kamanlend;
use App\Payment\Drivers\Smartis\Smartis;
use Shetabit\Multipay\Drivers\Parsian\Parsian;

$packageConfig = require dirname(__DIR__).'/vendor/shetabit/multipay/config/payment.php';

$customConfig = [
    'default' => env('PAYMENT_DEFAULT_GATEWAY', 'parsian'),

    'drivers' => [
        'parsian' => [
            'apiPurchaseUrl' => env('PARSIAN_API_PURCHASE_URL', 'https://pec.shaparak.ir/NewIPGServices/Sale/SaleService.asmx?wsdl'),
            'apiPaymentUrl' => env('PARSIAN_API_PAYMENT_URL', 'https://pec.shaparak.ir/NewIPG/'),
            'apiVerificationUrl' => env('PARSIAN_API_VERIFICATION_URL', 'https://pec.shaparak.ir/NewIPGServices/Confirm/ConfirmService.asmx?wsdl'),
            'merchantId' => env('PARSIAN_MERCHANT_ID', ''),
            'callbackUrl' => '',
            'description' => 'پرداخت از طریق درگاه پارسیان',
            'currency' => 'R',
        ],
        'digipay' => [
            'apiPaymentUrl' => env('DIGIPAY_API_URL', 'https://api.mydigipay.com'),
            'username' => env('DIGIPAY_USERNAME', ''),
            'password' => env('DIGIPAY_PASSWORD', ''),
            'client_id' => env('DIGIPAY_CLIENT_ID', ''),
            'client_secret' => env('DIGIPAY_CLIENT_SECRET', ''),
            'callbackUrl' => '',
            'currency' => 'R',
        ],
        'kamanlend' => [
            'apiRegisterPaymentUrl' => env('KAMANLEND_API_REGISTER_URL', 'https://gateway.kamanlend.ir/api/Gateway/RegisterPayment'),
            'apiGetPaymentStateUrl' => env('KAMANLEND_API_STATE_URL', 'https://gateway.kamanlend.ir/api/Gateway/GetPaymentState'),
            'gatewayUrl' => env('KAMANLEND_GATEWAY_URL', 'https://gateway.kamanlend.ir'),
            'terminalCode' => env('KAMANLEND_TERMINAL_CODE', ''),
            'terminalSecret' => env('KAMANLEND_TERMINAL_SECRET', ''),
            'callbackUrl' => '',
        ],
        'smartis' => [
            'apiAuthTokenUrl' => env('SMARTIS_AUTH_URL', 'https://api.smartispay.app/auth-service/v1.0/get-token-ipg'),
            'apiCreatePaymentUrl' => env('SMARTIS_CREATE_PAYMENT_URL', 'https://api.smartispay.app/wallet-service/v1.0/legal-ipg/get-payment-url-foreign'),
            'apiStatusPaymentUrl' => env('SMARTIS_STATUS_URL', 'https://api.smartispay.app/wallet-service/v1.0/status-personal-payment'),
            'apiVerifyPaymentUrl' => env('SMARTIS_VERIFY_URL', 'https://api.smartispay.app/wallet-service/v1.0/verify-payment'),
            'paymentPageUrl' => env('SMARTIS_PAYMENT_PAGE_URL', 'https://wpg.smartispay.app'),
            'username' => env('SMARTIS_USERNAME', ''),
            'password' => env('SMARTIS_PASSWORD', ''),
            'terminalId' => env('SMARTIS_TERMINAL_ID', ''),
            'secretKey' => env('SMARTIS_SECRET_KEY', ''),
            'useIpg' => true,
            'callbackUrl' => '',
        ],
    ],

    'map' => [
        'parsian' => Parsian::class,
        'digipay' => CustomDigipay::class,
        'kamanlend' => Kamanlend::class,
        'smartis' => Smartis::class,
    ],
];

$merged = array_merge($packageConfig, $customConfig);
$merged['drivers'] = array_merge($packageConfig['drivers'] ?? [], $customConfig['drivers']);
$merged['map'] = $customConfig['map'];

return $merged;
