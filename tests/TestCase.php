<?php

namespace Zairakai\LaravelActivity\Tests;

use Illuminate\Database\Schema\Blueprint;
use Orchestra\Testbench\TestCase as Orchestra;
use Spatie\Activitylog\ActivitylogServiceProvider;
use Zairakai\LaravelActivity\LaravelActivityServiceProvider;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->loadLaravelMigrations();
        $this->createActivityLogTable();
        $this->artisan('migrate', ['--database' => 'testing']);
    }

    protected function createActivityLogTable(): void
    {
        resolve('db')->connection('testing')->getSchemaBuilder()->create('activity_log', function (Blueprint $blueprint): void {
            $blueprint->bigIncrements('id');
            $blueprint->string('log_name')->nullable();
            $blueprint->text('description');
            $blueprint->nullableMorphs('subject', 'subject');
            $blueprint->nullableMorphs('causer', 'causer');
            $blueprint->json('properties')->nullable();
            $blueprint->uuid('batch_uuid')->nullable();
            $blueprint->timestamps();

            $blueprint->index('log_name');
            $blueprint->index('subject_type');
            $blueprint->index('subject_id');
            $blueprint->index('causer_type');
            $blueprint->index('causer_id');
        });
    }

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
