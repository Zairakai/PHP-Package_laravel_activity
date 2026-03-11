<?php

declare(strict_types=1);

namespace Zairakai\LaravelActivity\Traits;

use Zairakai\LaravelActivity\Support\ActivityQueryFluent;

/**
 * Trait for querying model activities.
 */
trait TraceActivities
{
    /**
     * Get fluent query builder for activities.
     */
    public function activities(): ActivityQueryFluent
    {
        return new ActivityQueryFluent($this);
    }
}
