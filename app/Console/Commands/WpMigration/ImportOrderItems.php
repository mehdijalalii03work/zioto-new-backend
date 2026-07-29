<?php

namespace App\Console\Commands\WpMigration;

use App\Services\WpMigration\ProductMapper;
use App\Services\WpMigration\WpDatabase;
use Illuminate\Console\Command;
use Modules\Order\Models\OrderItem;

class ImportOrderItems extends Command
{
    protected $signature = 'migrate:wp-order-items
                            {--dry-run : Only show stats without inserting}';

    protected $description = 'Import WordPress order items into Laravel';

    public function handle(WpDatabase $wp, ProductMapper $productMapper): int
    {
        if (! $wp->table('order_mapping')->exists()) {
            $this->error('Order mapping table not found. Run migrate:wp-orders first.');

            return Command::FAILURE;
        }

        $totalItems = $wp->table('woocommerce_order_items')->where('order_item_type', 'line_item')->count();
        $mappedOrders = $wp->table('order_mapping')->count();

        $this->line("WordPress order items (line items): $totalItems");
        $this->line("Mapped orders: $mappedOrders");

        $imported = 0;
        $skipped = 0;

        $wp->table('woocommerce_order_items')
            ->where('order_item_type', 'line_item')
            ->orderBy('order_item_id')
            ->chunk(100, function ($items) use ($wp, $productMapper, &$imported, &$skipped) {
                $orderIds = $items->pluck('order_id')->unique()->toArray();
                $wpOrders = $wp->table('wc_orders')->whereIn('id', $orderIds)->get()->keyBy('id');

                foreach ($items as $item) {
                    $itemMeta = $wp->getAllMeta($item->order_item_id, 'woocommerce_order_itemmeta');
                    $laravelOrderId = $this->getLaravelOrderId($wp, $item->order_id);

                    if (! $laravelOrderId) {
                        $skipped++;

                        continue;
                    }

                    $wpProductId = $itemMeta['_product_id'] ?? null;
                    $laravelProductId = $wpProductId ? $productMapper->getLaravelId((int) $wpProductId) : null;

                    $quantity = (int) ($itemMeta['_qty'] ?? 1);
                    $lineTotal = (int) ($itemMeta['_line_total'] ?? 0);
                    $unitPrice = $quantity > 0 ? (int) round($lineTotal / $quantity) : $lineTotal;
                    $subtotal = $lineTotal;

                    if ($this->option('dry-run')) {
                        $imported++;

                        continue;
                    }

                    $wpOrder = $wpOrders->get($item->order_id);
                    $orderDate = $wpOrder?->date_created_gmt ?? $wpOrder?->date_updated_gmt ?? now();

                    $orderItem = new OrderItem;
                    $orderItem->timestamps = false;
                    $orderItem->order_id = $laravelOrderId;
                    $orderItem->product_id = $laravelProductId;
                    $orderItem->product_name = $item->order_item_name;
                    $orderItem->product_price = $unitPrice;
                    $orderItem->quantity = $quantity;
                    $orderItem->subtotal = $subtotal;
                    $orderItem->created_at = $orderDate;
                    $orderItem->updated_at = $orderDate;
                    $orderItem->save();

                    $imported++;
                }
            });

        $this->newLine();
        $this->info("Import complete: $imported items imported, $skipped skipped");

        return Command::SUCCESS;
    }

    protected function getLaravelOrderId(WpDatabase $wp, int $wpOrderId): ?int
    {
        $map = $wp->table('order_mapping')->where('wp_order_id', $wpOrderId)->first();

        return $map?->laravel_order_id;
    }
}
