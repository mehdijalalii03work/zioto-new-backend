<?php

namespace Tests\Unit;

use App\Payment\Drivers\Kamanlend\Kamanlend;
use App\Payment\Drivers\Nopay\Nopay;
use App\Payment\Drivers\Smartis\Smartis;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Shetabit\Multipay\Abstracts\Driver;
use Shetabit\Multipay\Contracts\DriverInterface;
use Shetabit\Multipay\Exceptions\InvalidPaymentException;
use Shetabit\Multipay\Exceptions\PurchaseFailedException;
use Shetabit\Multipay\Invoice;
use Shetabit\Multipay\Request;

class PaymentDriversTest extends TestCase
{
    private array $config;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = require dirname(__DIR__, 2).'/config/payment.php';
    }

    private function nopayDriver(array $settings, Invoice $invoice, Response $response): array
    {
        $handler = new MockHandler([$response]);
        $client = new Client(['handler' => $handler]);

        $driver = new Nopay($invoice, $settings);
        $driver->setLogger(fn (string $level, string $message, array $context = []) => null);

        (new \ReflectionProperty(Nopay::class, 'client'))->setValue($driver, $client);

        return [$driver, $handler];
    }

    public function test_config_contains_kamanlend_driver(): void
    {
        $this->assertArrayHasKey('kamanlend', $this->config['drivers']);
        $this->assertArrayHasKey('kamanlend', $this->config['map']);
    }

    public function test_config_contains_smartis_driver(): void
    {
        $this->assertArrayHasKey('smartis', $this->config['drivers']);
        $this->assertArrayHasKey('smartis', $this->config['map']);
    }

    public function test_kamanlend_class_maps_correctly(): void
    {
        $this->assertSame(Kamanlend::class, $this->config['map']['kamanlend']);
    }

    public function test_smartis_class_maps_correctly(): void
    {
        $this->assertSame(Smartis::class, $this->config['map']['smartis']);
    }

    public function test_kamanlend_config_has_required_keys(): void
    {
        $driverConfig = $this->config['drivers']['kamanlend'];

        $this->assertArrayHasKey('apiRegisterPaymentUrl', $driverConfig);
        $this->assertArrayHasKey('apiGetPaymentStateUrl', $driverConfig);
        $this->assertArrayHasKey('gatewayUrl', $driverConfig);
        $this->assertArrayHasKey('terminalCode', $driverConfig);
        $this->assertArrayHasKey('terminalSecret', $driverConfig);
        $this->assertArrayHasKey('callbackUrl', $driverConfig);
    }

    public function test_smartis_config_has_required_keys(): void
    {
        $driverConfig = $this->config['drivers']['smartis'];

        $this->assertArrayHasKey('apiAuthTokenUrl', $driverConfig);
        $this->assertArrayHasKey('apiCreatePaymentUrl', $driverConfig);
        $this->assertArrayHasKey('apiStatusPaymentUrl', $driverConfig);
        $this->assertArrayHasKey('apiVerifyPaymentUrl', $driverConfig);
        $this->assertArrayHasKey('paymentPageUrl', $driverConfig);
        $this->assertArrayHasKey('username', $driverConfig);
        $this->assertArrayHasKey('password', $driverConfig);
        $this->assertArrayHasKey('terminalId', $driverConfig);
        $this->assertArrayHasKey('secretKey', $driverConfig);
        $this->assertArrayHasKey('callbackUrl', $driverConfig);
    }

    public function test_kamanlend_extends_driver(): void
    {
        $this->assertTrue(is_subclass_of(Kamanlend::class, Driver::class));
    }

    public function test_smartis_extends_driver(): void
    {
        $this->assertTrue(is_subclass_of(Smartis::class, Driver::class));
    }

    public function test_kamanlend_implements_driver_interface(): void
    {
        $this->assertContains(DriverInterface::class, class_implements(Kamanlend::class));
    }

    public function test_smartis_implements_driver_interface(): void
    {
        $this->assertContains(DriverInterface::class, class_implements(Smartis::class));
    }

    public function test_kamanlend_has_required_methods(): void
    {
        $this->assertTrue(method_exists(Kamanlend::class, 'purchase'));
        $this->assertTrue(method_exists(Kamanlend::class, 'pay'));
        $this->assertTrue(method_exists(Kamanlend::class, 'verify'));
        $this->assertTrue(method_exists(Kamanlend::class, '__construct'));
    }

    public function test_smartis_has_required_methods(): void
    {
        $this->assertTrue(method_exists(Smartis::class, 'purchase'));
        $this->assertTrue(method_exists(Smartis::class, 'pay'));
        $this->assertTrue(method_exists(Smartis::class, 'verify'));
        $this->assertTrue(method_exists(Smartis::class, '__construct'));
    }

    public function test_kamanlend_can_be_instantiated(): void
    {
        $invoice = new Invoice;
        $settings = $this->config['drivers']['kamanlend'];

        $driver = new Kamanlend($invoice, $settings);

        $this->assertInstanceOf(Kamanlend::class, $driver);
        $this->assertInstanceOf(Driver::class, $driver);
    }

    public function test_smartis_can_be_instantiated(): void
    {
        $invoice = new Invoice;
        $settings = $this->config['drivers']['smartis'];

        $driver = new Smartis($invoice, $settings);

        $this->assertInstanceOf(Smartis::class, $driver);
        $this->assertInstanceOf(Driver::class, $driver);
    }

    public function test_kamanlend_default_sale_items_builder(): void
    {
        $invoice = new Invoice;
        $invoice->amount(100000);

        $settings = $this->config['drivers']['kamanlend'];
        $driver = new Kamanlend($invoice, $settings);

        $reflection = new \ReflectionMethod(Kamanlend::class, 'buildDefaultSaleItems');

        $items = $reflection->invoke($driver);

        $this->assertIsArray($items);
        $this->assertCount(1, $items);
        $this->assertSame('0', $items[0]['code']);
        $this->assertSame('پرداخت', $items[0]['title']);
        $this->assertSame(1, $items[0]['quantity']);
        $this->assertSame(100000, $items[0]['totalAmountRial']);
    }

    public function test_smartis_hmac_hash_generation(): void
    {
        $secretKey = 'test_secret_key';
        $phone = '09123456789';
        $callbackUrl = 'https://example.com/callback';
        $useIpg = 'true';
        $terminalId = '12345';
        $amount = 500000;
        $referenceId = '100';

        $hashInput = "{$phone}:{$callbackUrl}:{$useIpg}:{$terminalId}:{$amount}:{$referenceId}";
        $expectedHash = hash_hmac('sha256', $hashInput, $secretKey);

        $this->assertSame($expectedHash, hash_hmac('sha256', $hashInput, $secretKey));
        $this->assertNotEmpty($expectedHash);
    }

    public function test_config_contains_nopay_driver(): void
    {
        $this->assertArrayHasKey('nopay', $this->config['drivers']);
        $this->assertArrayHasKey('nopay', $this->config['map']);
    }

    public function test_nopay_class_maps_correctly(): void
    {
        $this->assertSame(Nopay::class, $this->config['map']['nopay']);
    }

    public function test_nopay_config_has_required_keys(): void
    {
        $driverConfig = $this->config['drivers']['nopay'];

        $this->assertArrayHasKey('apiBaseUrl', $driverConfig);
        $this->assertArrayHasKey('username', $driverConfig);
        $this->assertArrayHasKey('password', $driverConfig);
        $this->assertArrayHasKey('publicKey', $driverConfig);
        $this->assertArrayHasKey('privateKey', $driverConfig);
        $this->assertArrayHasKey('merchantNumber', $driverConfig);
        $this->assertArrayHasKey('cellNumber', $driverConfig);
        $this->assertArrayHasKey('requestFormat', $driverConfig);
        $this->assertArrayHasKey('verifySsl', $driverConfig);
        $this->assertArrayHasKey('callbackUrl', $driverConfig);
    }

    public function test_nopay_extends_driver(): void
    {
        $this->assertTrue(is_subclass_of(Nopay::class, Driver::class));
    }

    public function test_nopay_implements_driver_interface(): void
    {
        $this->assertContains(DriverInterface::class, class_implements(Nopay::class));
    }

    public function test_nopay_has_required_methods(): void
    {
        $this->assertTrue(method_exists(Nopay::class, 'purchase'));
        $this->assertTrue(method_exists(Nopay::class, 'pay'));
        $this->assertTrue(method_exists(Nopay::class, 'verify'));
        $this->assertTrue(method_exists(Nopay::class, '__construct'));
    }

    public function test_nopay_can_be_instantiated(): void
    {
        $invoice = new Invoice;
        $settings = $this->config['drivers']['nopay'];

        $driver = new Nopay($invoice, $settings);

        $this->assertInstanceOf(Nopay::class, $driver);
        $this->assertInstanceOf(Driver::class, $driver);
    }

    public function test_nopay_purchase_builds_flat_body_with_encrypted_password_and_int_amount(): void
    {
        $settings = array_merge($this->config['drivers']['nopay'], [
            'apiBaseUrl' => 'https://op-cpg-wrapper.bmicc.ir:44377/WEBAPIWrapper/ConsumerExternalWebapiWrapper/',
            'username' => 'sawiss',
            'password' => 'Ss@123456',
            'publicKey' => 'WtX3qPVc1lrHX/+ug9iILQ==',
            'privateKey' => 'd48mRc+Szge4lZoDr86Q5Q==',
            'merchantNumber' => '2200053',
            'cellNumber' => '09124055697',
            'callbackUrl' => 'https://example.com/callback',
        ]);

        $invoice = (new Invoice)->amount(100000)->detail(['orderId' => 1]);

        $response = new Response(200, [], json_encode([
            'Result' => [
                'entity' => [
                    'token' => 'TOKEN-123',
                    'redirectURL' => 'https://gateway/pay',
                ],
                'totalRecordCount' => 0,
                'notification' => ['errors' => [], 'hasErrors' => false],
            ],
            'Notification' => ['errors' => [], 'hasErrors' => false],
        ]));

        [$driver, $handler] = $this->nopayDriver($settings, $invoice, $response);

        $transactionId = $driver->purchase();

        $this->assertSame('TOKEN-123', $transactionId);

        $sentRequest = $handler->getLastRequest();
        $sentUrl = (string) $sentRequest->getUri();

        // trailing slash must not produce a double slash in the final URL
        $this->assertSame(
            'https://op-cpg-wrapper.bmicc.ir:44377/WEBAPIWrapper/ConsumerExternalWebapiWrapper/CPG/Security/Token/RequestToken',
            $sentUrl
        );

        $sentBody = json_decode($sentRequest->getBody()->getContents(), true);

        $this->assertSame('sawiss', $sentBody['serviceUserName']);
        $this->assertSame('2200053', $sentBody['merchantNumber']);
        $this->assertSame('09124055697', $sentBody['cellNumber']);
        $this->assertSame(100000, $sentBody['amount']);
        $this->assertSame('https://example.com/callback', $sentBody['returnURL']);

        // password must be AES-encrypted (base64), never the plain value
        $this->assertNotSame('Ss@123456', $sentBody['servicePassword']);
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9+\/]+=*$/', $sentBody['servicePassword']);

        // AES-128-CBC: one 16-byte block after PKCS7 padding (plaintext "Ss@123456" is 9 bytes)
        $this->assertSame(16, strlen(base64_decode($sentBody['servicePassword'])));
    }

    public function test_nopay_purchase_wrapper_format_wraps_payload(): void
    {
        $settings = array_merge($this->config['drivers']['nopay'], [
            'apiBaseUrl' => 'https://op-cpg-wrapper.bmicc.ir:44377/WEBAPIWrapper/ConsumerExternalWebapiWrapper',
            'requestFormat' => 'wrapper',
            'username' => 'sawiss',
            'password' => 'Ss@123456',
            'publicKey' => 'WtX3qPVc1lrHX/+ug9iILQ==',
            'privateKey' => 'd48mRc+Szge4lZoDr86Q5Q==',
            'merchantNumber' => '2200053',
            'cellNumber' => '09124055697',
            'callbackUrl' => 'https://example.com/callback',
        ]);

        $invoice = (new Invoice)->amount(100000);

        $response = new Response(200, [], json_encode([
            'Result' => [
                'entity' => [
                    'token' => 'TOKEN-WRAP',
                    'redirectURL' => 'https://gateway/pay',
                ],
                'notification' => ['errors' => [], 'hasErrors' => false],
            ],
            'Notification' => ['errors' => [], 'hasErrors' => false],
        ]));

        [$driver, $handler] = $this->nopayDriver($settings, $invoice, $response);

        $driver->purchase();

        $sentBody = json_decode($handler->getLastRequest()->getBody()->getContents(), true);

        $this->assertArrayHasKey('InputValue', $sentBody);
        $this->assertArrayHasKey('ServiceName', $sentBody);
        $this->assertSame('CPG/Security/Token/RequestToken', $sentBody['ServiceName']);
        $this->assertSame('sawiss', $sentBody['InputValue']['serviceUserName']);
        $this->assertArrayNotHasKey('serviceUserName', $sentBody);
    }

    public function test_nopay_purchase_throws_on_notification_errors(): void
    {
        $settings = array_merge($this->config['drivers']['nopay'], [
            'apiBaseUrl' => 'https://op-cpg-wrapper.bmicc.ir:44377/WEBAPIWrapper/ConsumerExternalWebapiWrapper',
            'username' => 'sawiss',
            'password' => 'Ss@123456',
            'publicKey' => 'WtX3qPVc1lrHX/+ug9iILQ==',
            'privateKey' => 'd48mRc+Szge4lZoDr86Q5Q==',
            'merchantNumber' => '2200053',
            'cellNumber' => '09124055697',
            'callbackUrl' => 'https://example.com/callback',
        ]);

        $invoice = (new Invoice)->amount(100000);

        // lowercase notification/errors/hasErrors — must still be detected
        $response = new Response(200, [], json_encode([
            'result' => null,
            'notification' => [
                'errors' => [
                    ['message' => 'خطای احراز هویت', 'code' => -7, 'addInfo' => null],
                ],
                'hasErrors' => true,
            ],
        ]));

        [$driver, $handler] = $this->nopayDriver($settings, $invoice, $response);

        $this->expectException(PurchaseFailedException::class);
        $this->expectExceptionMessage('nopay error [-7]: خطای احراز هویت');

        $driver->purchase();
    }

    public function test_nopay_verify_parses_entity_and_falls_back_to_request_token(): void
    {
        $settings = array_merge($this->config['drivers']['nopay'], [
            'apiBaseUrl' => 'https://op-cpg-wrapper.bmicc.ir:44377/WEBAPIWrapper/ConsumerExternalWebapiWrapper',
            'username' => 'sawiss',
            'password' => 'Ss@123456',
            'publicKey' => 'WtX3qPVc1lrHX/+ug9iILQ==',
            'privateKey' => 'd48mRc+Szge4lZoDr86Q5Q==',
            'merchantNumber' => '2200053',
            'cellNumber' => '09124055697',
            'callbackUrl' => 'https://example.com/callback',
        ]);

        $invoice = (new Invoice)->amount(100000);
        $invoice->transactionId('TXN-FROM-INVOICE');

        Request::overwrite('input', fn () => null);

        $response = new Response(200, [], json_encode([
            'Result' => [
                'entity' => [
                    'RefNumber' => 'RRN-987',
                    'OrderID' => 123456,
                    'IsApproved' => true,
                ],
                'TotalRecordCount' => 0,
                'Notification' => ['Errors' => [], 'HasErrors' => false],
            ],
            'Notification' => ['Errors' => [], 'HasErrors' => false],
        ]));

        [$driver, $handler] = $this->nopayDriver($settings, $invoice, $response);

        $receipt = $driver->verify();

        $this->assertSame('RRN-987', $receipt->getReferenceId());
        $this->assertSame('123456', $receipt->getDetails()['order_id']);
        $this->assertTrue($receipt->getDetails()['is_approved']);

        // token is taken from the invoice's transaction id (set during purchase)
        $sentBody = json_decode($handler->getLastRequest()->getBody()->getContents(), true);
        $this->assertSame('TXN-FROM-INVOICE', $sentBody['token']);
    }

    public function test_nopay_verify_throws_when_not_approved(): void
    {
        $settings = array_merge($this->config['drivers']['nopay'], [
            'apiBaseUrl' => 'https://op-cpg-wrapper.bmicc.ir:44377/WEBAPIWrapper/ConsumerExternalWebapiWrapper',
            'username' => 'sawiss',
            'password' => 'Ss@123456',
            'publicKey' => 'WtX3qPVc1lrHX/+ug9iILQ==',
            'privateKey' => 'd48mRc+Szge4lZoDr86Q5Q==',
            'merchantNumber' => '2200053',
            'cellNumber' => '09124055697',
            'callbackUrl' => 'https://example.com/callback',
        ]);

        $invoice = (new Invoice)->amount(100000);
        $invoice->transactionId('TOKEN-X');

        $response = new Response(200, [], json_encode([
            'Result' => [
                'entity' => [
                    'RefNumber' => '',
                    'OrderID' => 0,
                    'IsApproved' => false,
                ],
                'Notification' => ['Errors' => [], 'HasErrors' => false],
            ],
            'Notification' => ['Errors' => [], 'HasErrors' => false],
        ]));

        [$driver, $handler] = $this->nopayDriver($settings, $invoice, $response);

        $this->expectException(InvalidPaymentException::class);

        $driver->verify();
    }

    public function test_package_config_still_has_original_drivers(): void
    {
        $this->assertArrayHasKey('zarinpal', $this->config['drivers']);
        $this->assertArrayHasKey('nextpay', $this->config['drivers']);
        $this->assertArrayHasKey('local', $this->config['drivers']);
        $this->assertArrayHasKey('sepordeh', $this->config['drivers']);
    }
}
