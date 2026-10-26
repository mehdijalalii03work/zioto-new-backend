<?php

namespace Tests\Feature\Observers;

use App\Enums\OrderSmsType;
use App\Jobs\SendOrderSms;
use App\Models\OrderShipping;
use App\Models\ShippingMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Modules\Order\Models\Order;
use Tests\TestCase;

class OrderSmsNotificationObserverTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirmed_paid_shipping_order_queues_health_message_after_commit(): void
    {
        $order = $this->orderWithShipping(isPickup: false, status: 'pending', paymentStatus: 'paid');
        Queue::fake([SendOrderSms::class]);

        $order->update(['status' => 'confirmed']);

        Queue::assertPushed(SendOrderSms::class, fn (SendOrderSms $job): bool => $job->orderId === $order->id
            && $job->type === OrderSmsType::ReceivedHealth
            && $job->afterCommit === true
        );
    }

    public function test_completed_paid_shipping_order_queues_health_message_after_commit(): void
    {
        $order = $this->orderWithShipping(isPickup: false, status: 'confirmed', paymentStatus: 'paid');
        Queue::fake([SendOrderSms::class]);

        $order->update(['status' => 'completed']);

        Queue::assertPushed(SendOrderSms::class, fn (SendOrderSms $job): bool => $job->orderId === $order->id
            && $job->type === OrderSmsType::ReceivedHealth
            && $job->afterCommit === true
        );
    }

    public function test_pickup_completion_does_not_queue_received_health_message(): void
    {
        $order = $this->orderWithShipping(isPickup: true, status: 'confirmed', paymentStatus: 'paid');
        Queue::fake([SendOrderSms::class]);

        $order->update(['status' => 'completed']);

        Queue::assertNotPushed(SendOrderSms::class);
    }

    public function test_unpaid_order_completion_does_not_queue_received_health_message(): void
    {
        $order = $this->orderWithShipping(isPickup: false, status: 'pending', paymentStatus: 'pending');
        Queue::fake([SendOrderSms::class]);

        $order->update(['status' => 'completed']);

        Queue::assertNotPushed(SendOrderSms::class);
    }

    private function orderWithShipping(bool $isPickup, string $status, string $paymentStatus): Order
    {
        $user = User::factory()->create(['phone' => '09120000000']);
        $order = Order::factory()->for($user)->create([
            'status' => $status,
            'payment_status' => $paymentStatus,
        ]);
        $shippingMethod = ShippingMethod::factory()->create(['is_pickup' => $isPickup]);
        OrderShipping::create([
            'order_id' => $order->id,
            'shipping_method_id' => $shippingMethod->id,
            'shipping_method_name' => $shippingMethod->name,
            'shipping_cost' => 0,
        ]);

        return $order;
    }
}
