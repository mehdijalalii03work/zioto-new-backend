<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class TestLogFilePermission extends Command
{
    protected $signature = 'log:test-permission';

    protected $description = 'Create a test file in the logs directory to check file permissions';

    public function handle(): int
    {
        $logDir = storage_path('logs');

        if (! is_dir($logDir)) {
            $this->error("Log directory does not exist: {$logDir}");

            return static::FAILURE;
        }

        $permissions = substr(sprintf('%o', fileperms($logDir)), -4);
        $owner = posix_getpwuid(fileowner($logDir));
        $group = posix_getgrgid(filegroup($logDir));

        $this->info("Log directory: {$logDir}");
        $this->info("Directory permissions: {$permissions}");
        $this->info('Owner: '.($owner['name'] ?? 'unknown'));
        $this->info('Group: '.($group['name'] ?? 'unknown'));
        $this->newLine();

        $testFile = $logDir.'/test-'.Str::random(16).'.log';

        try {
            file_put_contents($testFile, 'Test file created at '.now()->toIso8601String().PHP_EOL);

            $filePerms = substr(sprintf('%o', fileperms($testFile)), -4);
            $fileOwner = posix_getpwuid(fileowner($testFile));
            $fileGroup = posix_getgrgid(filegroup($testFile));

            $this->info('✓ Test file created successfully:');
            $this->info("  File: {$testFile}");
            $this->info("  Permissions: {$filePerms}");
            $this->info('  Owner: '.($fileOwner['name'] ?? 'unknown'));
            $this->info('  Group: '.($fileGroup['name'] ?? 'unknown'));

            unlink($testFile);
            $this->info('✓ Test file cleaned up successfully.');

            return static::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('✗ Failed to create test file:');
            $this->error('  Error: '.$e->getMessage());

            return static::FAILURE;
        }
    }
}
