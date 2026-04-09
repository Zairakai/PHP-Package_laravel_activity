<?php

declare(strict_types=1);

namespace Zairakai\LaravelActivity\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Activity;

/**
 * Fluent API for querying activities.
 *
 * @template TActivity of Activity
 */
class ActivityQueryFluent
{
    /**
     * @param class-string<TActivity> $activityClass
     */
    public function __construct(
        protected readonly Model $model,
        protected readonly string $activityClass,
    ) {}

    /**
     * Get all activities (caused by + performed on).
     *
     * @return Builder<TActivity>
     */
    public function all(): Builder
    {
        $by = $this->by();
        $by->union($this->on());

        return $by;
    }

    /**
     * Get activities caused by this model.
     *
     * @return Builder<TActivity>
     */
    public function by(): Builder
    {
        return $this->activityClass::query()
            ->where('causer_type', $this->model::class)
            ->where('causer_id', $this->model->getKey());
    }

    /**
     * Get activities performed on this model.
     *
     * @return Builder<TActivity>
     */
    public function on(): Builder
    {
        return $this->activityClass::query()
            ->where('subject_type', $this->model::class)
            ->where('subject_id', $this->model->getKey());
    }
}
