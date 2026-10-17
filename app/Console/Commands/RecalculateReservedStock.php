<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\Product\Models\Product;

#[Signature('hesabfa:recalculate-reserved')]
#[Description('بازکالیبراسیون موجودی رزرو شده از روی سفارش‌های فعال')]
class RecalculateReservedStock extends Command
{
    public function handle(): int
    {
        $this->info('شروع بازکالیبراسیون موجودی رزرو شده...');

        $reservedStatuses = ['confirmed'];
        $totals = DB::table('order_items')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->select(
                DB::raw('COALESCE(products.base_product_id, order_items.product_id) as stock_product_id'),
                DB::raw('SUM(order_items.quantity * products.pack_size) as total_reserved')
            )
            ->whereIn('order_items.order_id', function ($q) use ($reservedStatuses) {
                $q->select('id')
                    ->from('orders')
                    ->whereIn('status', $reservedStatuses);
            })
            ->whereNotNull('order_items.product_id')
            ->groupBy('stock_product_id')
            ->get()
            ->keyBy('stock_product_id');

        $products = Product::all();
        $updated = 0;

        foreach ($products as $product) {
            $calculatedReserved = (int) ($totals[$product->id]->total_reserved ?? 0);

            if ($product->hesabfa_reserved_stock != $calculatedReserved) {
                $this->line(
                    "محصول {$product->sku}: {$product->hesabfa_reserved_stock} → {$calculatedReserved}"
                );

                $product->updateQuietly([
                    'hesabfa_reserved_stock' => $calculatedReserved,
                ]);
                $updated++;
            }
        }

        $this->info("بازکالیبراسیون کامل شد. {$updated} محصول بروزرسانی شدند.");

        return self::SUCCESS;
    }
}
