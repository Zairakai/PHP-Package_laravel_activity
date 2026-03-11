<?php

declare(strict_types=1);

namespace Zairakai\LaravelActivity\Support;

/**
 * Immutable value object representing a before/after set of pivot IDs.
 *
 * Used internally to compute which IDs were added or removed during
 * an attach, detach, or sync operation.
 */
final readonly class PivotChangeSet
{
    /**
     * @param array<int, int|string> $before
     * @param array<int, int|string> $after
     */
    public function __construct(
        public array $before,
        public array $after,
    ) {}

    /**
     * IDs present in after but not in before.
     *
     * @return array<int, int|string>
     */
    public function added(): array
    {
        return array_values(array_diff($this->after, $this->before));
    }

    /**
     * Returns true when any ID was added or removed.
     */
    public function hasChanges(): bool
    {
        if ($this->added() !== []) {
            return true;
        }

        return $this->removed() !== [];
    }

    /**
     * IDs present in before but not in after.
     *
     * @return array<int, int|string>
     */
    public function removed(): array
    {
        return array_values(array_diff($this->before, $this->after));
    }
}
