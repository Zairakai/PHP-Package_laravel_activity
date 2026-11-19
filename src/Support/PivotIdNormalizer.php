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
     * @param array<int|string, mixed>|Collection<int, Model>|Model|int|string $ids
     *
     * @return array<int, int|string>
     */
    public static function normalize(mixed $ids): array
    {
        return array_keys(self::records($ids));
    }

    /**
     * The IDs with their pivot attributes, as Laravel reads them: [1, 2] or [1 => ['role' => 'a'], 2 => []].
     *
     * @param array<int|string, mixed>|Collection<int, Model>|Model|int|string $ids
     *
     * @return array<int|string, array<string, mixed>>
     */
    public static function records(mixed $ids): array
    {
        $items = match (true) {
            $ids instanceof Collection => $ids->all(),
            is_array($ids)             => $ids,
            default                    => [$ids],
        };

        $records = [];

        foreach ($items as $key => $value) {
            if (is_array($value)) {
                /** @var array<string, mixed> $attributes */
                $attributes = $value;

                $records[self::extractKey($key)] = $attributes;

                continue;
            }

            $records[self::extractKey($value)] = [];
        }

        return $records;
    }

    /**
     * @throws LogicException When the resolved key is neither int nor string
     */
    private static function extractKey(mixed $value): int|string
    {
        $key = $value;

        if ($value instanceof Model) {
            $key = $value->getKey();
        }

        if (is_int($key) || is_string($key)) {
            return $key;
        }

        throw new LogicException('Pivot key must be int or string');
    }
}
