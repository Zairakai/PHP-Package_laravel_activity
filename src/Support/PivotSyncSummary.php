<?php

declare(strict_types=1);

namespace Zairakai\LaravelActivity\Support;

/**
 * Formats the result of a BelongsToMany::sync() call into a human-readable summary.
 *
 * Example output: "attached 2, detached 1, updated 3"
 */
final class PivotSyncSummary
{
    /**
     * Build a summary string from a sync() result array.
     *
     * Returns an empty string when nothing changed.
     *
     * @param array{
     *     attached: array<int, int|string>,
     *     detached: array<int, int|string>,
     *     updated:  array<int, int|string>
     * } $result
     */
    public static function fromResult(array $result): string
    {
        $parts = [];

        if (count($result['attached']) > 0) {
            $parts[] = 'attached ' . count($result['attached']);
        }

        if (count($result['detached']) > 0) {
            $parts[] = 'detached ' . count($result['detached']);
        }

        if (count($result['updated']) > 0) {
            $parts[] = 'updated ' . count($result['updated']);
        }

        return implode(', ', $parts);
    }
}
