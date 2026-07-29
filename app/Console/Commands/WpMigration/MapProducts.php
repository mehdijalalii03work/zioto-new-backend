<?php

namespace App\Console\Commands\WpMigration;

use App\Services\WpMigration\ProductMapper;
use App\Services\WpMigration\WpDatabase;
use Illuminate\Console\Command;

class MapProducts extends Command
{
    protected $signature = 'migrate:wp-products-map
                            {--dry-run : Only show stats without inserting}';

    protected $description = 'Map WordPress products to Laravel products by SKU';

    public function handle(WpDatabase $wp, ProductMapper $mapper): int
    {
        if (! $wp->table('posts')->where('post_type', 'product')->exists()) {
            $this->error('WordPress database not accessible. Check wp_data connection.');

            return Command::FAILURE;
        }

        $wpProductCount = $wp->table('posts')->where('post_type', 'product')->count();
        $laravelProductCount = \DB::table('products')->count();

        $this->line("WordPress products: $wpProductCount");
        $this->line("Laravel products: $laravelProductCount");

        if ($this->option('dry-run')) {
            $mapped = \DB::table('products')
                ->joinSub(
                    $wp->table('posts')->where('post_type', 'product')->select('ID'),
                    'wp',
                    function ($join) {
                        $join->on(\DB::raw('1'), '=', \DB::raw('1'));
                    }
                )
                ->count();

            $this->line('[DRY-RUN] No changes made.');

            return Command::SUCCESS;
        }

        $inserted = $mapper->buildMap();
        $totalMapped = $mapper->getTotalMapped();

        $this->info("Inserted: $inserted product SKU mappings");
        $this->info("Total mapped products: $totalMapped");

        return Command::SUCCESS;
    }
}
