<?php

declare(strict_types=1);

namespace Zairakai\LaravelActivity\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use LogicException;

/**
 * Normalizes heterogeneous ID inputs into a flat array of scalar keys.
 *
 * Accepts int, string, Model, array, or Collection and always returns
 * a plain array<int, int|string> suitable for pivot operations.
 */
final class PivotIdNormalizer
{
    /**
     * Normalize any ID input into a flat scalar array.
     *
     * @param array<int|string>|Collection<int, Model>|Model|int|string $ids
     *
     * @return array<int, int|string>
     */
    public static function normalize(mixed $ids): array
    {
        $collection = match (true) {
            $ids instanceof Collection => $ids,
            $ids instanceof Model      => collect([$ids->getKey()]),
            is_array($ids)             => collect($ids),
            default                    => collect([$ids]),
        };

        return $collection
            ->map(fn ($id): int|string => self::extractKey($id))
            ->values()
            ->all();
    }

    /**
     * @throws LogicException When the resolved key is neither int nor string
     */
    private static function extractKey(mixed $value): int|string
    {
        $key = $value instanceof Model ? $value->getKey() : $value;

        if (is_int($key) || is_string($key)) {
            return $key;
        }

        throw new LogicException('Pivot key must be int or string');
    }
}
