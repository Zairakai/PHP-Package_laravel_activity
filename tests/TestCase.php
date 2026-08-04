<?php

declare(strict_types=1);

namespace Zairakai\LaravelActivity\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;
use Spatie\Activitylog\ActivitylogServiceProvider;
use Zairakai\LaravelActivity\LaravelActivityServiceProvider;

class TestCase extends Orchestra
{
    protected function defineDatabaseMigrations(): void
    {
        Schema::connection('testing')->create('activity_log', function (Blueprint $blueprint): void {
            $blueprint->bigIncrements('id');
            $blueprint->string('log_name')->nullable();
            $blueprint->text('description');
            $blueprint->nullableMorphs('subject', 'subject');
            $blueprint->string('event')->nullable();
            $blueprint->nullableMorphs('causer', 'causer');
            $blueprint->json('attribute_changes')->nullable();
            $blueprint->json('properties')->nullable();
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

    protected function rollbackDatabaseMigrations(): void
    {
        Schema::connection('testing')->dropIfExists('activity_log');
    }
}
