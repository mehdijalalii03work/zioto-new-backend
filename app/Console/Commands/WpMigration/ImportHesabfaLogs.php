<?php

namespace App\Console\Commands\WpMigration;

use App\Models\HesabfaSyncLog;
use App\Services\WpMigration\WpDatabase;
use Illuminate\Console\Command;

class ImportHesabfaLogs extends Command
{
    protected $signature = 'migrate:wp-hesabfa-logs
                            {--dry-run : Only show stats without inserting}';

    protected $description = 'Import WordPress Hesabfa sync logs into Laravel';

    public function handle(WpDatabase $wp): int
    {
        if (! $wp->table('order_mapping')->exists()) {
            $this->error('Order mapping table not found. Run migrate:wp-orders first.');

            return Command::FAILURE;
        }

        $totalLogs = $wp->table('zioto_hesabfa_sync_log')->count();
        $this->line("WordPress Hesabfa logs: $totalLogs");

        $imported = 0;
        $skipped = 0;

        $wp->table('zioto_hesabfa_sync_log')
            ->orderBy('id')
            ->chunk(100, function ($logs) use ($wp, &$imported, &$skipped) {
                foreach ($logs as $log) {
                    $laravelOrderId = $this->getLaravelOrderId($wp, $log->order_id);

                    if (! $laravelOrderId) {
                        $skipped++;

                        continue;
                    }

                    if ($this->option('dry-run')) {
                        $imported++;

                        continue;
                    }

                    $responseData = null;
                    if (! empty($log->response_data)) {
                        $decoded = json_decode($log->response_data, true);
                        $responseData = $decoded !== null ? $decoded : $log->response_data;
                    }

                    $syncLog = new HesabfaSyncLog;
                    $syncLog->timestamps = false;
                    $syncLog->order_id = $laravelOrderId;
                    $syncLog->sync_type = $log->sync_type;
                    $syncLog->status = $log->status;
                    $syncLog->request_data = null;
                    $syncLog->response_data = $responseData;
                    $syncLog->error_message = $log->error_message ?? null;
                    $syncLog->created_at = $log->created_at;
                    $syncLog->updated_at = $log->updated_at;
                    $syncLog->save();

                    $imported++;
                }
            });

        $this->newLine();
        $this->info("Import complete: $imported logs imported, $skipped skipped (no order mapping)");

        return Command::SUCCESS;
    }

    protected function getLaravelOrderId(WpDatabase $wp, int $wpOrderId): ?int
    {
        $map = $wp->table('order_mapping')->where('wp_order_id', $wpOrderId)->first();

        return $map?->laravel_order_id;
    }
}
