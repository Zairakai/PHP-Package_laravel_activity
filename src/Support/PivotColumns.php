<?php

declare(strict_types=1);

namespace Zairakai\LaravelActivity\Support;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * The columns of a pivot table that are never written in the log: the sensitive ones, which cannot be recreated.
 *
 * They come from the configuration (hidden_columns), from the $hidden of the pivot model of the relation
 * (use_pivot_hidden), and from the columns given to the call itself.
 */
final class PivotColumns
{
    /**
     * @param BelongsToMany<*, *, *, *> $relation
     * @param array<int, string> $extra
     *
     * @return array<int, string>
     */
    public static function hidden(BelongsToMany $relation, array $extra = []): array
    {
        $columns = config()->array('laravel-activity.hidden_columns', []);

        if (config()->boolean('laravel-activity.use_pivot_hidden', true)) {
            $pivotClass = $relation->getPivotClass();
            $columns    = [...$columns, ...(new $pivotClass)->getHidden()];
        }

        return array_values(array_filter([...$columns, ...$extra], is_string(...)));
    }
}
