<?php

declare(strict_types=1);

namespace Zairakai\LaravelActivity\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Zairakai\LaravelActivity\Support\PivotSyncSummary;

final class PivotSyncSummaryTest extends TestCase
{
    #[Test]
    public function empty_result_returns_empty_string(): void
    {
        $result = ['attached' => [], 'detached' => [], 'updated' => []];

        $this->assertSame('', PivotSyncSummary::fromResult($result));
    }

    #[Test]
    public function formats_each_section(): void
    {
        $result = ['attached' => [1, 2], 'detached' => [3], 'updated' => [4, 5, 6]];

        $this->assertSame(
            'attached 2, detached 1, updated 3',
            PivotSyncSummary::fromResult($result),
        );
    }

    #[Test]
    public function only_some_sections_present(): void
    {
        $result = ['attached' => [], 'detached' => [3], 'updated' => []];

        $this->assertSame(
            'detached 1',
            PivotSyncSummary::fromResult($result),
        );
    }
}
