<?php

declare(strict_types=1);

namespace Zairakai\LaravelActivity\Tests\Unit;

use Illuminate\Support\ServiceProvider;
use PHPUnit\Framework\Attributes\Test;
use Zairakai\LaravelActivity\LaravelActivityServiceProvider;
use Zairakai\LaravelActivity\Tests\UnitTestCase;

final class LaravelActivityServiceProviderTest extends UnitTestCase
{
    #[Test]
    public function it_merges_the_default_configuration(): void
    {
        self::assertSame('pivot', config('laravel-activity.log_name'));
        self::assertTrue(config('laravel-activity.use_pivot_hidden'));
        self::assertSame(
            ['password', 'remember_token', 'token', 'api_token', 'secret'],
            config('laravel-activity.hidden_columns'),
        );
    }

    #[Test]
    public function it_publishes_the_configuration_under_two_tags(): void
    {
        foreach (['laravel-activity', 'laravel-activity-config'] as $tag) {
            $paths = ServiceProvider::pathsToPublish(LaravelActivityServiceProvider::class, $tag);

            self::assertCount(1, $paths);
            self::assertFileExists((string) array_key_first($paths));
        }
    }
}
