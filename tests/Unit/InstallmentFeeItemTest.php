<?php

namespace Tests\Unit;

use App\Observers\HesabfaObserver;
use App\Services\HesabfaService;
use Modules\Order\Models\Order;
use PHPUnit\Framework\TestCase;

/**
 * Verifies that the Hesabfa invoice fee row is only added for installment
 * gateways that actually charge a fee ('installment'), and NOT for
 * fee-free installment gateways like nopay ('installment_nofee').
 */
class InstallmentFeeItemTest extends TestCase
{
    private function makeOrder(string $paymentMethod): Order
    {
        $order = new Order;
        $order->payment_method = $paymentMethod;

        return $order;
    }

    private function shouldAddFee(Order $order): bool
    {
        $observer = new HesabfaObserver($this->createMock(HesabfaService::class));

        $method = new \ReflectionMethod(HesabfaObserver::class, 'shouldAddInstallmentFee');
        $method->setAccessible(true);

        return $method->invoke($observer, $order);
    }

    public function test_installment_with_fee_adds_fee_row(): void
    {
        $this->assertTrue($this->shouldAddFee($this->makeOrder('installment')));
    }

    public function test_installment_nofee_does_not_add_fee_row(): void
    {
        $this->assertFalse($this->shouldAddFee($this->makeOrder('installment_nofee')));
    }

    public function test_online_does_not_add_fee_row(): void
    {
        $this->assertFalse($this->shouldAddFee($this->makeOrder('online')));
    }
}
