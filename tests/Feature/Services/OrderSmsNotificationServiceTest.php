<?php

namespace Tests\Feature\Services;

use App\Enums\OrderSmsType;
use App\Models\OrderShipping;
use App\Models\ShippingMethod;
use App\Models\User;
use App\Services\OrderSmsNotificationService;
use App\Services\SmsIrService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Order\Models\Order;
use Tests\TestCase;

class OrderSmsNotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_paid_pickup_order_uses_order_number_amount_and_shamsi_date_once(): void
    {
        $user = User::factory()->create([
            'name' => 'Sara Rahimi',
            'first_name' => 'Sara',
            'phone' => '09120000000',
        ]);
        $order = Order::factory()->for($user)->create([
            'order_number' => '00123',
            'status' => 'confirmed',
            'payment_status' => 'paid',
            'total_amount' => 123456,
            'created_at' => '2025-03-20 09:30:00',
        ]);
        $pickupMethod = ShippingMethod::factory()->pickup()->create();
        $shipping = OrderShipping::create([
            'order_id' => $order->id,
            'shipping_method_id' => $pickupMethod->id,
            'shipping_method_name' => $pickupMethod->name,
            'shipping_cost' => 0,
        ]);

        $sms = $this->mock(SmsIrService::class, function ($mock): void {
            $mock->shouldReceive('sendPickupOrderConfirmation')
                ->with('09120000000', 'Sara', '00123', 123456, '1403/12/30')
                ->once()
                ->andReturn(true);
        });

        $result = app(OrderSmsNotificationService::class)->send($order->id, OrderSmsType::Confirmation);

        $this->assertTrue($result);
        $this->assertNotNull($shipping->fresh()->order_confirmation_sms_sent_at);
    }

    public function test_shipping_order_confirmation_uses_the_shipping_template(): void
    {
        $user = User::factory()->create([
            'name' => 'Sara Rahimi',
            'first_name' => 'Sara',
            'phone' => '09120000000',
        ]);
        $order = Order::factory()->for($user)->create([
            'order_number' => '00124',
            'status' => 'confirmed',
            'payment_status' => 'paid',
            'total_amount' => 765432,
            'created_at' => '2025-03-20 09:30:00',
        ]);
        $shippingMethod = ShippingMethod::factory()->create(['is_pickup' => false]);
        $shipping = OrderShipping::create([
            'order_id' => $order->id,
            'shipping_method_id' => $shippingMethod->id,
            'shipping_method_name' => $shippingMethod->name,
            'shipping_cost' => 0,
        ]);

        $this->mock(SmsIrService::class, function ($mock): void {
            $mock->shouldReceive('sendShippingOrderConfirmation')
                ->with('09120000000', 'Sara', '00124', 765432, '1403/12/30')
                ->once()
                ->andReturn(true);
        });

        $result = app(OrderSmsNotificationService::class)->send($order->id, OrderSmsType::Confirmation);

        $this->assertTrue($result);
        $this->assertNotNull($shipping->fresh()->order_confirmation_sms_sent_at);
    }

    public function test_received_health_message_is_sent_only_for_completed_shipping_orders(): void
    {
        $user = User::factory()->create([
            'name' => 'Sara Rahimi',
            'first_name' => 'Sara',
            'phone' => '09120000000',
        ]);
        $order = Order::factory()->for($user)->create([
            'status' => 'completed',
            'payment_status' => 'paid',
        ]);
        $shippingMethod = ShippingMethod::factory()->create(['is_pickup' => false]);
        $shipping = OrderShipping::create([
            'order_id' => $order->id,
            'shipping_method_id' => $shippingMethod->id,
            'shipping_method_name' => $shippingMethod->name,
            'shipping_cost' => 0,
        ]);

        $this->mock(SmsIrService::class, function ($mock): void {
            $mock->shouldReceive('sendOrderReceivedHealthMessage')
                ->with('09120000000', 'Sara')
                ->once()
                ->andReturn(true);
        });

        $result = app(OrderSmsNotificationService::class)->send($order->id, OrderSmsType::ReceivedHealth);

        $this->assertTrue($result);
        $this->assertNotNull($shipping->fresh()->received_health_sms_sent_at);
    }

    public function test_failed_sms_does_not_mark_the_notification_as_sent(): void
    {
        $user = User::factory()->create(['phone' => '09120000000']);
        $order = Order::factory()->for($user)->create([
            'status' => 'confirmed',
            'payment_status' => 'paid',
        ]);
        $pickupMethod = ShippingMethod::factory()->pickup()->create();
        $shipping = OrderShipping::create([
            'order_id' => $order->id,
            'shipping_method_id' => $pickupMethod->id,
            'shipping_method_name' => $pickupMethod->name,
            'shipping_cost' => 0,
        ]);

        $this->mock(SmsIrService::class, function ($mock): void {
            $mock->shouldReceive('sendPickupOrderConfirmation')->once()->andReturn(false);
        });

        $result = app(OrderSmsNotificationService::class)->send($order->id, OrderSmsType::Confirmation);

        $this->assertFalse($result);
        $this->assertNull($shipping->fresh()->order_confirmation_sms_sent_at);
    }
}
