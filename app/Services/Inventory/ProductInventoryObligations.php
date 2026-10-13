<?php

namespace App\Services\Inventory;

use Illuminate\Database\Connection;
use Illuminate\Database\Connectors\ConnectionFactory;
use Illuminate\Database\Query\Builder;
use Modules\Product\Models\Product;

final class ProductInventoryObligations
{
    /**
     * The caller owns the product mutex, also held by order submission before
     * inserting snapshots. A separate writer autocommit read bypasses an outer
     * REPEATABLE READ snapshot without acquiring order/item locks after products.
     * The caller's query also sees its own uncommitted obligations. An older
     * visible obligation can conservatively require retrying in a new transaction.
     */
    public function hasOpenOrders(Product $product): bool
    {
        $connection = $product->getConnection();
        if ($connection->getDriverName() !== 'mysql') {
            return $this->query($connection, $product->id)->exists();
        }

        $writerConfig = $connection->getConfig();
        unset($writerConfig['name'], $writerConfig['read'], $writerConfig['write'], $writerConfig['url']);
        $fresh = app(ConnectionFactory::class)->make($writerConfig, 'inventory_product_obligations');
        try {
            if ($this->query($fresh, $product->id)->useWritePdo()->exists()) {
                return true;
            }

            return $this->query($connection, $product->id)->useWritePdo()->exists();
        } finally {
            $fresh->disconnect();
        }
    }

    private function query(Connection $connection, int $productId): Builder
    {
        return $connection->table('order_items')->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('order_items.product_id', $productId)->whereNull('orders.deleted_at')
            ->where(function (Builder $query): void {
                $query->where(function (Builder $query): void {
                    $query->whereNotIn('orders.status', ['completed', 'cancelled', 'refunded', 'expired'])
                        ->where(fn (Builder $query) => $query->whereNull('orders.serial_fulfillment_status')->orWhereNotIn('orders.serial_fulfillment_status', ['shipped', 'returned']));
                })->orWhere(function (Builder $query): void {
                    $query->where('order_items.made_to_order', true)->whereNull('order_items.production_completed_at')
                        ->where('order_items.serial_fulfillment_status', 'awaiting_production');
                });
            });
    }
}
