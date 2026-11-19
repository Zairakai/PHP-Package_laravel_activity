<?php

declare(strict_types=1);

namespace Zairakai\LaravelActivity\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Zairakai\LaravelActivity\Support\PivotDiff;

final class PivotDiffTest extends TestCase
{
    #[Test]
    public function a_modified_row_is_in_both(): void
    {
        $pivotDiff = new PivotDiff([1 => ['by' => 'a']], [1 => ['by' => 'b']]);

        self::assertSame([1 => ['by' => 'a']], $pivotDiff->old());
        self::assertSame([1 => ['by' => 'b']], $pivotDiff->new());
    }

    #[Test]
    public function a_removed_row_is_only_in_old(): void
    {
        $pivotDiff = new PivotDiff([1 => ['role_id' => 1]], []);

        self::assertSame([1 => ['role_id' => 1]], $pivotDiff->old());
        self::assertSame([], $pivotDiff->new());
        self::assertFalse($pivotDiff->isEmpty());
    }

    #[Test]
    public function an_added_row_is_only_in_new(): void
    {
        $pivotDiff = new PivotDiff([], [2 => ['role_id' => 2]]);

        self::assertSame([], $pivotDiff->old());
        self::assertSame([2 => ['role_id' => 2]], $pivotDiff->new());
        self::assertFalse($pivotDiff->isEmpty());
    }

    #[Test]
    public function an_unchanged_row_is_left_out(): void
    {
        $pivotDiff = new PivotDiff([1 => ['by' => 'a'], 2 => ['by' => 'a']], [1 => ['by' => 'a']]);

        self::assertSame([2 => ['by' => 'a']], $pivotDiff->old());
        self::assertSame([], $pivotDiff->new());
    }

    #[Test]
    public function identical_snapshots_are_empty(): void
    {
        self::assertTrue(new PivotDiff([1 => ['by' => 'a']], [1 => ['by' => 'a']])->isEmpty());
        self::assertTrue(new PivotDiff([], [])->isEmpty());
    }
}
