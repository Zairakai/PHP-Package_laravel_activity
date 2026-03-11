<?php

declare(strict_types=1);

namespace Zairakai\LaravelActivity\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Zairakai\LaravelActivity\Support\PivotChangeSet;

final class PivotChangeSetTest extends TestCase
{
    // =========================================================================
    // hasChanges()
    // =========================================================================

    #[Test]
    public function it_detects_changes_when_ids_were_added(): void
    {
        $pivotChangeSet = new PivotChangeSet(before: [1], after: [1, 2]);

        $this->assertTrue($pivotChangeSet->hasChanges());
    }

    #[Test]
    public function it_detects_changes_when_ids_were_removed(): void
    {
        $pivotChangeSet = new PivotChangeSet(before: [1, 2], after: [1]);

        $this->assertTrue($pivotChangeSet->hasChanges());
    }

    #[Test]
    public function it_reports_no_changes_when_before_and_after_are_identical(): void
    {
        $pivotChangeSet = new PivotChangeSet(before: [1, 2, 3], after: [1, 2, 3]);

        $this->assertFalse($pivotChangeSet->hasChanges());
    }

    #[Test]
    public function it_reports_no_changes_when_both_sets_are_empty(): void
    {
        $pivotChangeSet = new PivotChangeSet(before: [], after: []);

        $this->assertFalse($pivotChangeSet->hasChanges());
    }

    #[Test]
    public function it_returns_all_after_ids_when_before_is_empty(): void
    {
        $pivotChangeSet = new PivotChangeSet(before: [], after: [1, 2, 3]);

        $this->assertSame([1, 2, 3], $pivotChangeSet->added());
    }

    #[Test]
    public function it_returns_all_before_ids_when_after_is_empty(): void
    {
        $pivotChangeSet = new PivotChangeSet(before: [1, 2, 3], after: []);

        $this->assertSame([1, 2, 3], $pivotChangeSet->removed());
    }

    #[Test]
    public function it_returns_empty_array_when_nothing_was_added(): void
    {
        $pivotChangeSet = new PivotChangeSet(before: [1, 2, 3], after: [1, 2]);

        $this->assertSame([], $pivotChangeSet->added());
    }

    #[Test]
    public function it_returns_empty_array_when_nothing_was_removed(): void
    {
        $pivotChangeSet = new PivotChangeSet(before: [1, 2], after: [1, 2, 3]);

        $this->assertSame([], $pivotChangeSet->removed());
    }

    // =========================================================================
    // added()
    // =========================================================================

    #[Test]
    public function it_returns_ids_present_in_after_but_not_before(): void
    {
        $pivotChangeSet = new PivotChangeSet(before: [1, 2], after: [2, 3, 4]);

        $this->assertSame([3, 4], $pivotChangeSet->added());
    }

    // =========================================================================
    // removed()
    // =========================================================================

    #[Test]
    public function it_returns_ids_present_in_before_but_not_after(): void
    {
        $pivotChangeSet = new PivotChangeSet(before: [1, 2, 3], after: [2, 3]);

        $this->assertSame([1], $pivotChangeSet->removed());
    }
}
