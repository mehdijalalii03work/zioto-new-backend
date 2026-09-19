<?php

namespace App\Console\Commands;

use App\Models\JibitApiLog;
use Carbon\Carbon;
use Illuminate\Console\Command;

class PruneJibitLogs extends Command
{
    protected $signature = 'jibit:prune-logs
                            {--days=30 : Number of days to keep logs}
                            {--dry-run : Show what would be deleted without making changes}';

    protected $description = 'Prune old Jibit API logs older than the specified number of days';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $dryRun = $this->option('dry-run');
        $cutoff = Carbon::now()->subDays($days);

        $count = JibitApiLog::where('created_at', '<', $cutoff)->count();

        if ($count === 0) {
            $this->info("No logs found older than {$days} days.");

            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->warn("DRY RUN - Would delete {$count} logs older than {$cutoff->format('Y/m/d H:i:s')}");

            return self::SUCCESS;
        }

        $deleted = JibitApiLog::where('created_at', '<', $cutoff)->delete();

        $this->info("Successfully deleted {$deleted} logs older than {$days} days.");

        return self::SUCCESS;
    }
}
