<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\ShahkarService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Morilog\Jalali\Jalalian;

class FetchUserIdentityInfo extends Command
{
    protected $signature = 'user:fetch-identity-info
                            {--dry-run : Show what would be done without making changes}
                            {--national-code= : Process only a specific national code}
                            {--limit= : Limit the number of users to process}';

    protected $description = 'Fetch identity info from Jibit for existing users who have not been verified yet';

    private int $successCount = 0;

    private int $mismatchCount = 0;

    private int $errorCount = 0;

    private int $skippedCount = 0;

    public function handle(ShahkarService $shahkar): int
    {
        $dryRun = $this->option('dry-run');
        $specificNationalCode = $this->option('national-code');
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;

        $query = User::withoutTenantScope()
            ->where('identity_verification_status', 'pending')
            ->whereNotNull('national_code')
            ->whereNotNull('birth_date')
            ->where('national_code', '!=', '');

        if ($specificNationalCode) {
            $query->where('national_code', $specificNationalCode);
        }

        if ($limit) {
            $query->take($limit);
        }

        $users = $query->get();

        $totalCount = $users->count();

        if ($totalCount === 0) {
            $this->info('No users found requiring identity verification.');

            return self::SUCCESS;
        }

        $this->info("Found {$totalCount} users to process.");
        $this->newLine();

        if ($dryRun) {
            $this->warn('DRY RUN - No changes will be made.');
            $this->newLine();
        }

        $progressBar = $this->output->createProgressBar($totalCount);
        $progressBar->start();

        foreach ($users as $user) {
            $shamsiDate = $this->gregorianToShamsi($user->birth_date);

            if (! $shamsiDate) {
                $this->errorCount++;
                $this->skippedCount++;
                $progressBar->advance();

                continue;
            }

            if (! $dryRun) {
                $result = $shahkar->getIdentityInfo($user->national_code, $shamsiDate);

                if ($result['success']) {
                    $info = $result['info'];
                    $user->update([
                        'first_name' => $info['first_name'] ?? $user->first_name,
                        'last_name' => $info['last_name'] ?? $user->last_name,
                        'name' => ($info['first_name'] ?? '').' '.($info['last_name'] ?? ''),
                        'father_name' => $info['father_name'] ?? $user->father_name,
                        'gender' => ShahkarService::mapGender($info['gender'] ?? $user->gender),
                        'birth_place' => $info['birth_place'] ?? $user->birth_place,
                        'identity_verification_status' => 'verified',
                        'identity_verified_at' => now(),
                    ]);
                    $this->successCount++;
                } elseif ($result['reason'] === 'birth_date_mismatch') {
                    $user->update([
                        'identity_verification_status' => 'birth_date_mismatch',
                    ]);
                    $this->mismatchCount++;
                } else {
                    $this->errorCount++;
                    Log::warning("Identity fetch failed for user {$user->id}", [
                        'reason' => $result['reason'] ?? 'unknown',
                    ]);
                }

                // Rate limit: 500ms delay between requests
                usleep(500_000);
            } else {
                $this->successCount++;
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        $this->info('--- Summary ---');
        $this->info("Total processed: {$totalCount}");
        $this->info("Successful: {$this->successCount}");
        $this->info("Birth date mismatch: {$this->mismatchCount}");
        $this->info("Errors: {$this->errorCount}");

        if ($dryRun) {
            $this->warn('DRY RUN - No changes were made.');
        }

        return self::SUCCESS;
    }

    private function gregorianToShamsi(string $gregorianDate): ?string
    {
        try {
            $date = new \DateTime($gregorianDate);
            $jalali = Jalalian::fromDateTime($date);

            return $jalali->format('Ymd');
        } catch (\Exception $e) {
            return null;
        }
    }
}
