<?php

declare(strict_types=1);

namespace Zairakai\LaravelActivity\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Spatie\Activitylog\ActivitylogServiceProvider;
use Zairakai\LaravelActivity\LaravelActivityServiceProvider;

/**
 * Lightweight base for unit tests that only need the app + query builder.
 *
 * Does not run migrations — no real DB connection is ever made.
 * Use TestCase when actual DB reads/writes are needed.
 */
class UnitTestCase extends Orchestra
{
    protected function getEnvironmentSetUp($app): void
    {
        config()->set('database.default', 'testing');
        config()->set('database.connections.testing', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);
    }

    protected function getPackageProviders($app): array
    {
        return [
            LaravelActivityServiceProvider::class,
            ActivitylogServiceProvider::class,
        ];
    }
}
