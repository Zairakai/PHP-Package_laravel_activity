<?php

declare(strict_types=1);

namespace Zairakai\LaravelActivity\Support;

/**
 * What changed between two snapshots of a pivot table: only the rows that were added, removed or modified.
 *
 * The rows before the change are in old(), the rows after it in new(), both keyed by the ID of the related model.
 * An added row is only in new(), a removed row only in old().
 */
final readonly class PivotDiff
{
    /**
     * @var array<int|string, array<string, mixed>>
     */
    private array $new;

    /**
     * @var array<int|string, array<string, mixed>>
     */
    private array $old;

    /**
     * @param array<int|string, array<string, mixed>> $before
     * @param array<int|string, array<string, mixed>> $after
     */
    public function __construct(array $before, array $after)
    {
        $old = [];
        $new = [];

        foreach (array_keys($before + $after) as $key) {
            $row = $before[$key] ?? null;
            $now = $after[$key]  ?? null;

            if ($row === $now) {
                continue;
            }

            if (null !== $row) {
                $old[$key] = $row;
            }

            if (null !== $now) {
                $new[$key] = $now;
            }
        }

        $this->old = $old;
        $this->new = $new;
    }

    public function isEmpty(): bool
    {
        if ([] !== $this->old) {
            return false;
        }

        return [] === $this->new;
    }

    /**
     * @return array<int|string, array<string, mixed>>
     */
    public function new(): array
    {
        return $this->new;
    }

    /**
     * @return array<int|string, array<string, mixed>>
     */
    public function old(): array
    {
        return $this->old;
    }
}
