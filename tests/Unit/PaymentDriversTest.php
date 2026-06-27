<?php

namespace Tests\Unit;

use App\Payment\Drivers\Kamanlend\Kamanlend;
use App\Payment\Drivers\Smartis\Smartis;
use PHPUnit\Framework\TestCase;
use Shetabit\Multipay\Abstracts\Driver;
use Shetabit\Multipay\Contracts\DriverInterface;
use Shetabit\Multipay\Invoice;

class PaymentDriversTest extends TestCase
{
    private array $config;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = require dirname(__DIR__, 2).'/config/payment.php';
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
        $invoice = new Invoice();
        $settings = $this->config['drivers']['kamanlend'];

        $driver = new Kamanlend($invoice, $settings);

        $this->assertInstanceOf(Kamanlend::class, $driver);
        $this->assertInstanceOf(Driver::class, $driver);
    }

    public function test_smartis_can_be_instantiated(): void
    {
        $invoice = new Invoice();
        $settings = $this->config['drivers']['smartis'];

        $driver = new Smartis($invoice, $settings);

        $this->assertInstanceOf(Smartis::class, $driver);
        $this->assertInstanceOf(Driver::class, $driver);
    }

    public function test_kamanlend_default_sale_items_builder(): void
    {
        $invoice = new Invoice();
        $invoice->amount(100000);

        $settings = $this->config['drivers']['kamanlend'];
        $driver = new Kamanlend($invoice, $settings);

        $reflection = new \ReflectionMethod(Kamanlend::class, 'buildDefaultSaleItems');
        $reflection->setAccessible(true);

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

    public function test_package_config_still_has_original_drivers(): void
    {
        $this->assertArrayHasKey('zarinpal', $this->config['drivers']);
        $this->assertArrayHasKey('nextpay', $this->config['drivers']);
        $this->assertArrayHasKey('local', $this->config['drivers']);
        $this->assertArrayHasKey('sepordeh', $this->config['drivers']);
    }
}
