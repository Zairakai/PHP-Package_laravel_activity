<?php

declare(strict_types=1);

namespace Zairakai\LaravelActivity\Tests\Unit;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Activitylog\Models\Activity;
use Zairakai\LaravelActivity\Support\ActivityQueryFluent;
use Zairakai\LaravelActivity\Tests\TestCase;

final class ActivityQueryFluentTest extends TestCase
{
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
    public function it_builds_all_query_as_union(): void
    {
        $builder = $this->activityQueryFluent->all();

        $this->assertInstanceOf(Builder::class, $builder);

        // Verify it's a union query
        $sql = $builder->toSql();
        $this->assertStringContainsString('union', $sql);
    }

    #[Test]
    public function it_builds_by_query(): void
    {
        $builder = $this->activityQueryFluent->by();

        $this->assertInstanceOf(Builder::class, $builder);

        // Verify query has correct where clauses
        $sql = $builder->toSql();
        $this->assertStringContainsString('causer_type', $sql);
        $this->assertStringContainsString('causer_id', $sql);

        // Verify model class is in bindings
        $bindings = $builder->getBindings();
        $this->assertContains(TestModel::class, $bindings);
    }

    #[Test]
    public function it_builds_on_query(): void
    {
        $builder = $this->activityQueryFluent->on();

        $this->assertInstanceOf(Builder::class, $builder);

        // Verify query has correct where clauses
        $sql = $builder->toSql();
        $this->assertStringContainsString('subject_type', $sql);
        $this->assertStringContainsString('subject_id', $sql);

        // Verify model class is in bindings
        $bindings = $builder->getBindings();
        $this->assertContains(TestModel::class, $bindings);
    }

    #[Test]
    public function it_uses_activity_model_for_queries(): void
    {
        $builder = $this->activityQueryFluent->by();
        $onQuery = $this->activityQueryFluent->on();

        $this->assertInstanceOf(Activity::class, $builder->getModel());
        $this->assertInstanceOf(Activity::class, $onQuery->getModel());
    }

    #[Test]
    public function it_uses_injected_activity_class_instead_of_base_activity(): void
    {
        $fluent = new ActivityQueryFluent($this->testModel, CustomActivity::class);

        $this->assertInstanceOf(CustomActivity::class, $fluent->by()->getModel());
        $this->assertInstanceOf(CustomActivity::class, $fluent->on()->getModel());
    }
}

/**
 * Test model for unit tests.
 */
class TestModel extends Model
{
    public $id;

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
