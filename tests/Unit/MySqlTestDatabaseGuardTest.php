<?php

namespace Tests\Unit;

use Illuminate\Foundation\Bootstrap\HandleExceptions;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class MySqlTestDatabaseGuardTest extends TestCase
{
    public static function unsafeConfigurations(): array
    {
        return [
            'URL overrides safe database name' => ['testing', 'mysql', 'looks_safe_test', 'mysql://root@127.0.0.1:1/unsafe_production'],
            'non-testing environment' => ['production', 'mysql', 'safe_test', 'mysql://root@127.0.0.1:1/safe_test'],
            'non-MySQL driver' => ['testing', 'sqlite', ':memory:', ''],
        ];
    }

    #[DataProvider('unsafeConfigurations')]
    public function test_unsafe_configuration_is_refused_before_connecting_or_migrating(string $environmentName, string $driver, string $database, string $url): void
    {
        $values = ['SERIAL_INVENTORY_MYSQL_TESTS' => '1', 'APP_ENV' => $environmentName, 'DB_CONNECTION' => $driver, 'DB_DATABASE' => $database, 'DB_URL' => $url];
        $previous = [];
        foreach ($values as $key => $value) {
            $previous[$key] = [getenv($key), $_ENV[$key] ?? null, $_SERVER[$key] ?? null];
            putenv($key.'='.$value);
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
        $bootstrap = new class('runTest') extends \Tests\TestCase
        {
            public function runTest(): void {}
        };
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Refusing');

        try {
            $bootstrap->createApplication();
        } finally {
            HandleExceptions::flushState($this);
            foreach ($previous as $key => [$environment, $env, $server]) {
                putenv($environment === false ? $key : $key.'='.$environment);
                if ($env === null) {
                    unset($_ENV[$key]);
                } else {
                    $_ENV[$key] = $env;
                }
                if ($server === null) {
                    unset($_SERVER[$key]);
                } else {
                    $_SERVER[$key] = $server;
                }
            }
        }
    }
}
