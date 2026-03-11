<?php

declare(strict_types=1);

namespace Zairakai\LaravelActivity\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Activity;

/**
 * Fluent API for querying activities.
 */
class ActivityQueryFluent
{
    public function __construct(
        protected Model $model,
    ) {}

    /**
     * Get all activities (caused by + performed on).
     *
     * @return Builder<Activity>
     */
    public function all(): Builder
    {
        $by = $this->by();
        $on = $this->on();

        return $by->union($on);
    }

    /**
     * Get activities caused by this model.
     *
     * @return Builder<Activity>
     */
    public function by(): Builder
    {
        $query = Activity::query();

        return $query
            ->where('causer_type', $this->model::class)
            ->where('causer_id', $this->model->getKey());
    }

    /**
     * Get activities performed on this model.
     *
     * @return Builder<Activity>
     */
    public function on(): Builder
    {
        $query = Activity::query();

        return $query
            ->where('subject_type', $this->model::class)
            ->where('subject_id', $this->model->getKey());
    }
}
