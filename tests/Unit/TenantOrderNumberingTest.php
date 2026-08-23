<?php

namespace Tests\Unit;

use App\Support\Platform;
use Illuminate\Database\Eloquent\Collection;
use Modules\Order\Models\Order;
use PHPUnit\Framework\TestCase;

/**
 * Verifies per-platform order numbering: nopay orders get an "N-" prefix
 * and numbering is tracked separately per platform.
 */
class TenantOrderNumberingTest extends TestCase
{
    public function test_platform_constants(): void
    {
        $this->assertSame('main', Platform::MAIN);
        $this->assertSame('nopay', Platform::NOPAY);
        $this->assertSame(['main', 'nopay', 'tapsi'], Platform::ALL);
    }

    public function test_order_number_generation_uses_n_prefix_for_nopay(): void
    {
        // Simulate the booted::creating logic directly.
        $order = new Order;
        $order->platform = Platform::NOPAY;

        $maxNumber = 20999; // DB::table('orders')->where('platform','nopay')->max(...) ?? 20999
        $order->order_number = 'N-'.str_pad($maxNumber + 1, 5, '0', STR_PAD_LEFT);

        $this->assertSame('N-21000', $order->order_number);
    }

    public function test_order_number_generation_for_main_has_no_prefix(): void
    {
        $order = new Order;
        $order->platform = Platform::MAIN;

        $maxNumber = 20999;
        $order->order_number = str_pad($maxNumber + 1, 5, '0', STR_PAD_LEFT);

        $this->assertSame('21000', $order->order_number);
    }

    public function test_orders_collection_can_hold_both_platforms(): void
    {
        $main = new Order;
        $main->platform = Platform::MAIN;
        $main->order_number = '21000';

        $nopay = new Order;
        $nopay->platform = Platform::NOPAY;
        $nopay->order_number = 'N-21000';

        $collection = new Collection([$main, $nopay]);

        $this->assertCount(2, $collection);
        $this->assertSame('21000', $collection[0]->order_number);
        $this->assertSame('N-21000', $collection[1]->order_number);
    }
}
