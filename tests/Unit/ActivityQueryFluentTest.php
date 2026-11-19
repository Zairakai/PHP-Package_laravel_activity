<?php

declare(strict_types=1);

namespace Zairakai\LaravelActivity\Tests\Unit;

use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Activitylog\Models\Activity;
use Zairakai\LaravelActivity\Support\ActivityQueryFluent;
use Zairakai\LaravelActivity\Tests\UnitTestCase;

final class ActivityQueryFluentTest extends UnitTestCase
{
    /**
     * @var ActivityQueryFluent<Activity>
     */
    private ActivityQueryFluent $activityQueryFluent;

    private TestModel $testModel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testModel     = new TestModel;
        $this->testModel->id = 123;

        $this->activityQueryFluent = new ActivityQueryFluent($this->testModel, Activity::class);
    }

    #[Test]
    public function it_builds_all_query_in_one_query(): void
    {
        $builder = $this->activityQueryFluent->all();

        // One query, without union: it can be counted and paginated
        $sql = $builder->toBase()->toSql();
        self::assertStringNotContainsString('union', $sql);
        self::assertStringContainsString(' or ', $sql);
    }

    #[Test]
    public function it_builds_by_query(): void
    {
        $builder = $this->activityQueryFluent->by();

        // Verify query has correct where clauses
        $sql = $builder->toBase()->toSql();
        self::assertStringContainsString('causer_type', $sql);
        self::assertStringContainsString('causer_id', $sql);

        // Verify model class is in bindings
        $bindings = $builder->toBase()->getBindings();
        self::assertContains(TestModel::class, $bindings);
    }

    #[Test]
    public function it_builds_on_query(): void
    {
        $builder = $this->activityQueryFluent->on();

        // Verify query has correct where clauses
        $sql = $builder->toBase()->toSql();
        self::assertStringContainsString('subject_type', $sql);
        self::assertStringContainsString('subject_id', $sql);

        // Verify model class is in bindings
        $bindings = $builder->toBase()->getBindings();
        self::assertContains(TestModel::class, $bindings);
    }

    #[Test]
    public function it_uses_activity_model_for_queries(): void
    {
        $builder = $this->activityQueryFluent->by();
        $onQuery = $this->activityQueryFluent->on();

        self::assertInstanceOf(Activity::class, $builder->getModel());
        self::assertInstanceOf(Activity::class, $onQuery->getModel());
    }

    #[Test]
    public function it_uses_injected_activity_class_instead_of_base_activity(): void
    {
        $activityQueryFluent = new ActivityQueryFluent($this->testModel, CustomActivity::class);

        self::assertInstanceOf(CustomActivity::class, $activityQueryFluent->by()->getModel());
        self::assertInstanceOf(CustomActivity::class, $activityQueryFluent->on()->getModel());
    }
}

/**
 * Test model for unit tests.
 *
 * @property int $id
 */
class TestModel extends Model
{
    protected $guarded = [];

    protected $table = 'test_models';
}

/**
 * Custom activity model stub for injection tests.
 */
class CustomActivity extends Activity
{
    protected $table = 'activity_log';
}
