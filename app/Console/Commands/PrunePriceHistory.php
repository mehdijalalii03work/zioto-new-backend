<?php

namespace App\Console\Commands;

use App\Models\PriceHistory;
use Carbon\Carbon;
use Illuminate\Console\Command;

class PrunePriceHistory extends Command
{
    protected $signature = 'pricehistory:prune
                            {--days=30 : Number of days to keep records}
                            {--dry-run : Show what would be deleted without making changes}';

    protected $description = 'Prune old price history records older than the specified number of days';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $dryRun = $this->option('dry-run');
        $cutoff = Carbon::now()->subDays($days);

        $count = PriceHistory::where('created_at', '<', $cutoff)->count();

        if ($count === 0) {
            $this->info("No records found older than {$days} days.");

            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->warn("DRY RUN - Would delete {$count} records older than {$cutoff->format('Y/m/d H:i:s')}");

            return self::SUCCESS;
        }

        $deleted = PriceHistory::where('created_at', '<', $cutoff)->delete();

        $this->info("Successfully deleted {$deleted} records older than {$days} days.");

        return self::SUCCESS;
    }
}
