<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        $app = parent::createApplication();

        if (getenv('SERIAL_INVENTORY_MYSQL_TESTS') === '1') {
            if (! $app->environment('testing') || DB::getDriverName() !== 'mysql' || ! str_ends_with(DB::connection()->getDatabaseName(), '_test')) {
                throw new \RuntimeException('Refusing to refresh a database outside the explicitly selected disposable MySQL test boundary.');
            }
            config([
                'pulse.storage.database.connection' => 'sqlite',
                'database.connections.sqlite.database' => ':memory:',
                'database.connections.sqlite.url' => null,
            ]);
            DB::purge('sqlite');
        }

        return $app;
    }
}
