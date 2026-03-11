<?php

declare(strict_types=1);

namespace Zairakai\LaravelActivity\Tests\Unit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;
use Zairakai\LaravelActivity\Support\PivotIdNormalizer;

final class PivotIdNormalizerTest extends TestCase
{
    #[Test]
    public function normalizes_array(): void
    {
        $this->assertSame([1, 2, 3], PivotIdNormalizer::normalize([1, 2, 3]));
    }

    #[Test]
    public function normalizes_collection_of_models(): void
    {
        $m1 = new class extends Model
        {
            protected $guarded = [];
        };
        $m1->id = 10;

        $m2 = new class extends Model
        {
            protected $guarded = [];
        };
        $m2->id = 20;

        $collection = new Collection([$m1, $m2]);

        $this->assertSame([10, 20], PivotIdNormalizer::normalize($collection));
    }

    #[Test]
    public function normalizes_scalar(): void
    {
        $this->assertSame([5], PivotIdNormalizer::normalize(5));
        $this->assertSame(['abc'], PivotIdNormalizer::normalize('abc'));
    }

    #[Test]
    public function throws_on_invalid_key(): void
    {
        $this->expectException(LogicException::class);
        PivotIdNormalizer::normalize([new stdClass()]);
    }
}
