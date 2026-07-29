<?php

namespace App\Console\Commands\WpMigration;

use App\Models\User;
use App\Services\WpMigration\WpDatabase;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;

class ImportUsers extends Command
{
    protected $signature = 'migrate:wp-users
                            {--dry-run : Only show stats without inserting}';

    protected $description = 'Import WordPress users into Laravel';

    public function handle(WpDatabase $wp): int
    {
        if (! $wp->table('users')->exists()) {
            $this->error('WordPress database not accessible.');

            return Command::FAILURE;
        }

        $totalInWp = $wp->table('users')->count();
        $existingInLaravel = User::count();

        $this->line("WordPress users: $totalInWp");
        $this->line("Existing Laravel users: $existingInLaravel");

        $imported = 0;
        $skipped = 0;
        $mapped = 0;
        $errors = 0;

        $this->ensureMappingTable($wp);

        $wp->table('users')
            ->orderBy('ID')
            ->chunk(100, function ($wpUsers) use ($wp, &$imported, &$skipped, &$mapped, &$errors, $totalInWp) {
                foreach ($wpUsers as $wpUser) {
                    $phone = $wp->getMeta($wpUser->ID, 'billing_phone', 'usermeta');

                    if (empty($phone)) {
                        $phone = $wp->getMeta($wpUser->ID, '_wplus_phone', 'usermeta');
                    }

                    if (empty($phone)) {
                        $skipped++;

                        continue;
                    }

                    $existingUser = User::where('phone', $phone)->first();

                    if ($existingUser) {
                        $this->saveMapping($wp, $wpUser->ID, $existingUser->id);
                        $mapped++;

                        continue;
                    }

                    if ($this->option('dry-run')) {
                        $imported++;

                        continue;
                    }

                    $result = $this->createUser($wp, $wpUser);

                    if ($result === null) {
                        $errors++;

                        continue;
                    }

                    [$user, $mappedStatus] = $result;

                    if ($mappedStatus) {
                        $mapped++;
                    } else {
                        $imported++;
                    }
                }

                $done = $imported + $skipped + $mapped + $errors;
                if ($totalInWp > 0) {
                    $progress = (int) ($done / $totalInWp * 100);
                    $this->line("Progress: $progress% ($imported imported, $skipped skipped, $mapped mapped, $errors errors)");
                }
            });

        $this->newLine();
        $this->info('Import complete:');
        $this->info("  Imported: $imported");
        $this->info("  Skipped (no phone): $skipped");
        $this->info("  Mapped (already exists): $mapped");
        $this->info("  Errors: $errors");

        $totalInLaravel = User::count();
        $this->line("Total Laravel users now: $totalInLaravel");

        return Command::SUCCESS;
    }

    protected function createUser(WpDatabase $wp, object $wpUser): ?array
    {
        $firstName = $wp->getMeta($wpUser->ID, 'first_name', 'usermeta');
        $lastName = $wp->getMeta($wpUser->ID, 'last_name', 'usermeta');
        $nationalCode = $wp->getMeta($wpUser->ID, 'national_code', 'usermeta');
        $shahkarVerified = $wp->getMeta($wpUser->ID, 'shahkar_verified', 'usermeta');
        $phone = $wp->getMeta($wpUser->ID, 'billing_phone', 'usermeta');

        if (empty($phone)) {
            $phone = $wp->getMeta($wpUser->ID, '_wplus_phone', 'usermeta');
        }

        $data = [
            'name' => $wpUser->display_name ?: ($firstName.' '.$lastName),
            'first_name' => $firstName ?: null,
            'last_name' => $lastName ?: null,
            'email' => ! empty($wpUser->user_email) ? $wpUser->user_email : null,
            'phone' => $phone,
            'password' => bcrypt(Str::random(32)),
            'created_at' => $wpUser->user_registered,
            'updated_at' => $wpUser->user_registered,
        ];

        if (! empty($nationalCode)) {
            $existing = User::where('national_code', $nationalCode)->where('phone', '!=', $phone)->exists();
            if (! $existing) {
                $data['national_code'] = $nationalCode;
                $data['shahkar_verified'] = $shahkarVerified === '1';
            }
        }

        if (! empty($data['email'])) {
            $existing = User::where('email', $data['email'])->where('phone', '!=', $phone)->exists();
            if ($existing) {
                $data['email'] = null;
            }
        }

        try {
            $user = new User;
            $user->timestamps = false;
            $user->forceFill($data);
            $user->save();

            $this->saveMapping($wp, $wpUser->ID, $user->id);

            return [$user, false];
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'Duplicate entry')) {
                $existing = User::where('phone', $phone)->first();

                if ($existing) {
                    $this->saveMapping($wp, $wpUser->ID, $existing->id);

                    return [$existing, true];
                }

                $data['national_code'] = null;
                $data['email'] = null;

                try {
                    $user = new User;
                    $user->timestamps = false;
                    $user->forceFill($data);
                    $user->save();

                    $this->saveMapping($wp, $wpUser->ID, $user->id);

                    return [$user, false];
                } catch (QueryException $e2) {
                    $this->warn("Failed to create user {$wpUser->ID} ({$phone}): ".$e2->getMessage());

                    return null;
                }
            }

            $this->warn("Error creating user {$wpUser->ID} ({$phone}): ".$e->getMessage());

            return null;
        }
    }

    protected function ensureMappingTable(WpDatabase $wp): void
    {
        $wp->ensureMappingTable('user_mapping', [
            'wp_user_id' => 'BIGINT UNSIGNED NOT NULL PRIMARY KEY',
            'laravel_user_id' => 'BIGINT UNSIGNED NOT NULL',
            'KEY laravel_user_id_idx (laravel_user_id)',
        ]);
    }

    protected function saveMapping(WpDatabase $wp, int $wpUserId, int $laravelUserId): void
    {
        $wp->saveMapping('user_mapping', [
            'wp_user_id' => $wpUserId,
            'laravel_user_id' => $laravelUserId,
        ]);
    }
}
