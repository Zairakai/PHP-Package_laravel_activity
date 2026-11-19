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
     * Get all activities (caused by + performed on), in one query.
     *
     * @return Builder<TActivity>
     */
    public function all(): Builder
    {
        return $this->activityClass::query()
            ->where(function (Builder $query): void {
                $query->where(fn (Builder $caused): Builder => $this->causedBy($caused))
                    ->orWhere(fn (Builder $performed): Builder => $this->performedOn($performed));
            });
    }

    /**
     * Get activities caused by this model.
     *
     * @return Builder<TActivity>
     */
    public function by(): Builder
    {
        return $this->causedBy($this->activityClass::query());
    }

    /**
     * Get activities performed on this model.
     *
     * @return Builder<TActivity>
     */
    public function on(): Builder
    {
        return $this->performedOn($this->activityClass::query());
    }

    /**
     * Get the pivot activities where this model was on the other side of the relation.
     *
     * For a Role: the entries of the users that got or lost it, found by the related type and the ID in the rows.
     *
     * @return Builder<TActivity>
     */
    public function related(): Builder
    {
        /** @var int|string $key */
        $key = $this->model->getKey();
        $key = (string) $key;

        $builder = $this->activityClass::query();
        $logName = config('laravel-activity.log_name');

        // The log name is indexed: it narrows the search before the JSON columns are read
        if (is_string($logName)) {
            $builder->where('log_name', $logName);
        }

        return $builder
            ->where('properties->related_type', $this->model->getMorphClass())
            ->where(function (Builder $query) use ($key): void {
                $query->getQuery()
                    ->whereJsonContainsKey('attribute_changes->attributes->' . $key)
                    ->orWhereJsonContainsKey('attribute_changes->old->' . $key);
            });
    }

    /**
     * @param Builder<TActivity> $builder
     *
     * @return Builder<TActivity>
     */
    private function causedBy(Builder $builder): Builder
    {
        return $builder
            ->where('causer_type', $this->model->getMorphClass())
            ->where('causer_id', $this->model->getKey());
    }

    /**
     * @param Builder<TActivity> $builder
     *
     * @return Builder<TActivity>
     */
    private function performedOn(Builder $builder): Builder
    {
        return $builder
            ->where('subject_type', $this->model->getMorphClass())
            ->where('subject_id', $this->model->getKey());
    }
}
