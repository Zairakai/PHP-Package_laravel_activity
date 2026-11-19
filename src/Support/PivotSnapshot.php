<?php

declare(strict_types=1);

namespace Zairakai\LaravelActivity\Support;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * The rows of the pivot table of a relation for its parent, keyed by the ID of the related model.
 *
 * The rows are read from the pivot table itself, with all their columns, and without loading the related models.
 * The constraints of the relation (wherePivot) are applied.
 */
final class PivotSnapshot
{
    /**
     * @param BelongsToMany<*, *, *, *> $relation
     * @param array<int, string>          $hidden Columns left out of the rows (never the key of the related model)
     * @param array<int, int|string>|null $ids    Only the rows of these related IDs, all the rows when null
     *
     * @return array<int|string, array<string, mixed>>
     */
    public static function take(BelongsToMany $relation, array $hidden = [], ?array $ids = null): array
    {
        $keyName = $relation->getRelatedPivotKeyName();
        $left    = array_flip(array_diff($hidden, [$keyName]));
        $query   = $relation->newPivotQuery();

        if (null !== $ids) {
            $query->whereIn($keyName, $ids);
        }

        $rows = [];

        foreach ($query->get() as $row) {
            /** @var array<string, mixed> $attributes */
            $attributes = (array) $row;
            $key        = $attributes[$keyName] ?? null;

            if (
                is_int($key)
                || is_string($key)
            ) {
                $rows[$key] = array_diff_key($attributes, $left);
            }
        }

        return $rows;
    }
}
