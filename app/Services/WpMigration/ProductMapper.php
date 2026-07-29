<?php

namespace App\Services\WpMigration;

use Illuminate\Support\Facades\DB;

class ProductMapper
{
    public function __construct(
        protected WpDatabase $wp
    ) {}

    public function buildMap(): int
    {
        $this->wp->ensureMappingTable('product_sku_map', [
            'wp_product_id BIGINT UNSIGNED NOT NULL PRIMARY KEY',
            'laravel_product_id BIGINT UNSIGNED NOT NULL',
            'sku VARCHAR(255) NOT NULL',
            'UNIQUE KEY laravel_product_id (laravel_product_id)',
            'INDEX sku_idx (sku)',
        ]);

        $wpProducts = $this->wp->table('posts')
            ->where('post_type', 'product')
            ->get();

        $inserted = 0;
        $skipped = 0;

        foreach ($wpProducts as $product) {
            $sku = $this->wp->getMeta($product->ID, '_sku');

            if (empty($sku)) {
                $skipped++;

                continue;
            }

            $laravelProduct = DB::table('products')->where('sku', $sku)->first();

            if (! $laravelProduct) {
                $skipped++;

                continue;
            }

            $this->wp->table('product_sku_map')->updateOrInsert(
                ['wp_product_id' => $product->ID],
                [
                    'wp_product_id' => $product->ID,
                    'laravel_product_id' => $laravelProduct->id,
                    'sku' => $sku,
                ]
            );

            $inserted++;
        }

        return $inserted;
    }

    public function getLaravelId(int $wpProductId): ?int
    {
        $map = $this->wp->table('product_sku_map')
            ->where('wp_product_id', $wpProductId)
            ->first();

        return $map?->laravel_product_id;
    }

    public function getTotalMapped(): int
    {
        return $this->wp->table('product_sku_map')->count();
    }
}
