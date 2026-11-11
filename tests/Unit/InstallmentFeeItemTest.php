<?php

namespace Tests\Unit;

use App\Observers\HesabfaObserver;
use App\Services\HesabfaService;
use Modules\Order\Models\Order;
use PHPUnit\Framework\TestCase;

/**
 * Verifies that fee distribution is limited to installment gateways that
 * actually charge a fee ('installment'), excluding fee-free gateways.
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

        return $method->invoke($observer, $order);
    }

    public function test_fee_charging_installment_is_eligible_for_fee_distribution(): void
    {
        $this->assertTrue($this->shouldAddFee($this->makeOrder('installment')));
    }

    public function test_fee_free_installment_is_not_eligible_for_fee_distribution(): void
    {
        $this->assertFalse($this->shouldAddFee($this->makeOrder('installment_nofee')));
    }

    public function test_online_payment_is_not_eligible_for_fee_distribution(): void
    {
        $this->assertFalse($this->shouldAddFee($this->makeOrder('online')));
    }
}
