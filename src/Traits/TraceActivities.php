<?php

declare(strict_types=1);

namespace Zairakai\LaravelActivity\Traits;

use Spatie\Activitylog\Models\Activity;
use Zairakai\LaravelActivity\Support\ActivityQueryFluent;

/**
 * Trait for querying model activities.
 */
trait TraceActivities
{
    /**
     * Get fluent query builder for activities.
     *
     * @return ActivityQueryFluent<Activity>
     */
    public function activities(): ActivityQueryFluent
    {
        /** @var class-string<Activity> $activityClass */
        $activityClass = config('activitylog.activity_model', Activity::class);

        return new ActivityQueryFluent($this, $activityClass);
    }
}
