<?php

namespace App\Console\Commands\WpMigration;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class VerifyMigration extends Command
{
    protected $signature = 'migrate:wp-verify';

    protected $description = 'Verify WordPress data migration results';

    public function handle(): int
    {
        $wp = DB::connection('wp_data');

        $this->line('=== WordPress Data Migration Verification ===');
        $this->newLine();

        $this->line('--- Mapping Tables ---');
        $this->showCount('Product SKU map', $wp, 'product_sku_map');
        $this->showCount('User mapping', $wp, 'user_mapping');
        $this->showCount('Order mapping', $wp, 'order_mapping');

        $this->newLine();
        $this->line('--- WordPress Source Data ---');
        $this->showCount('WP Users', $wp, 'users');
        $this->showCount('WP Orders (shop_order)', $wp, 'posts', function ($q) {
            return $q->where('post_type', 'shop_order');
        });
        $this->showCount('WP Orders (wc_orders)', $wp, 'wc_orders');
        $this->showCount('WP Order Items', $wp, 'woocommerce_order_items');
        $this->showCount('WP Pec Payments', $wp, 'pec_payments');

        $this->newLine();
        $this->line('--- Laravel Target Data ---');
        $this->showCount('Users', null, 'users');
        $this->showCount('User Addresses', null, 'user_addresses');
        $this->showCount('Orders', null, 'orders');
        $this->showCount('Order Items', null, 'order_items');
        $this->showCount('Payments', null, 'payments');
        $this->showCount('Hesabfa Sync Logs', null, 'hesabfa_sync_log');

        $this->newLine();
        $this->line('--- Order Numbers ---');
        $orderNumbers = DB::table('orders')
            ->selectRaw('MIN(CAST(order_number AS UNSIGNED)) as min_num')
            ->selectRaw('MAX(CAST(order_number AS UNSIGNED)) as max_num')
            ->first();
        $this->line("Order number range: {$orderNumbers->min_num} - {$orderNumbers->max_num}");

        $this->newLine();
        $this->line('--- Duplicate Check ---');
        $duplicatePhones = DB::table('users')
            ->select('phone', DB::raw('COUNT(*) as cnt'))
            ->whereNotNull('phone')
            ->groupBy('phone')
            ->having('cnt', '>', 1)
            ->count();
        $this->line("Users with duplicate phone numbers: $duplicatePhones");

        $orphanOrders = DB::table('orders')->whereNull('user_id')->count();
        $this->line("Orders with no user: $orphanOrders");

        $this->newLine();
        $this->info('Verification complete.');

        return Command::SUCCESS;
    }

    protected function showCount(string $label, $connection, string $table, ?callable $callback = null): void
    {
        $builder = $connection ? $connection->table($table) : DB::table($table);

        if ($callback) {
            $builder = $callback($builder);
        }

        $count = $builder->count();
        $source = $connection ? 'WP' : 'Laravel';
        $this->line("  $label ($source): $count");
    }
}
