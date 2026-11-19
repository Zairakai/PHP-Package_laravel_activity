<?php

declare(strict_types=1);

namespace Zairakai\LaravelActivity\Support;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Traits\Conditionable;
use Spatie\Activitylog\Support\ActivityLogger;
use Spatie\Activitylog\Support\ActivityLogStatus;

/**
 * Fluent API for pivot table activity logging.
 *
 * The pivot table has no model, so its history only exists here: each entry keeps the rows of the pivot table before
 * and after the operation, for the related models that changed, in the attribute_changes of Spatie (old and
 * attributes, as for a model), with the type of the related model in the properties. The operation is the event
 * of the entry. The sensitive columns (PivotColumns) are never written.
 *
 * @template TRelatedModel of Model
 * @template TParentModel of Model
 */
class PivotActivityFluent
{
    use Conditionable;

    protected Model|int|string|null $causedBy = null;

    /**
     * @var array<int, string>
     */
    protected array $hiddenColumns = [];

    protected ?string $logMessage = null;

    protected ?string $logName = null;

    protected bool $useAnonymousCauser = false;

    /**
     * @param BelongsToMany<TRelatedModel, TParentModel> $relation
     */
    public function __construct(protected BelongsToMany $relation) {}

    /**
     * Attach models to the pivot table with activity logging.
     *
     * The IDs that are already attached are skipped. The IDs can carry their own pivot attributes, as in Laravel:
     * [1 => ['role' => 'a'], 2 => []].
     *
     * @param array<int|string, mixed>|Collection<int, Model>|Model|int|string $ids
     * @param array<string, mixed>                                             $attributes
     */
    public function attach(mixed $ids, array $attributes = [], bool $touch = true): void
    {
        $this->atomic(function () use ($ids, $attributes, $touch): void {
            $records = PivotIdNormalizer::records($ids);
            $keys    = array_keys($records);
            $before  = $this->snapshot($keys);
            $new     = array_diff_key($records, $before);

            if ([] === $new) {
                return;
            }

            $this->relation->attach($new, $attributes, $touch);

            $this->record(
                'attached',
                $before,
                sprintf('Attached %d %s', count($new), $this->singular()),
                $keys,
            );
        });
    }

    /**
     * Set the causer of this activity, as Spatie does: a model, or the ID of one.
     *
     * Without a causer (or with null), Spatie decides: the authenticated user of its default_auth_driver.
     */
    public function by(Model|int|string|null $modelOrId = null): static
    {
        $this->causedBy           = $modelOrId;
        $this->useAnonymousCauser = false;

        return $this;
    }

    /**
     * Mark activity as anonymous (no causer).
     */
    public function byAnonymous(): static
    {
        $this->useAnonymousCauser = true;
        $this->causedBy           = null;

        return $this;
    }

    /**
     * Same as by(), under the name of Spatie.
     */
    public function causedBy(Model|int|string|null $modelOrId): static
    {
        return $this->by($modelOrId);
    }

    /**
     * Same as byAnonymous(), under the name of Spatie.
     */
    public function causedByAnonymous(): static
    {
        return $this->byAnonymous();
    }

    /**
     * Detach models from the pivot table with activity logging.
     *
     * @param array<int|string, mixed>|Collection<int, Model>|Model|int|string|null $ids   IDs to detach, or null to detach all
     * @param bool                                                                  $touch Whether to touch parent timestamps
     *
     * @return int Number of detached records
     */
    public function detach(mixed $ids = null, bool $touch = true): int
    {
        return $this->atomic(function () use ($ids, $touch): int {
            $scope = null;

            if (null !== $ids) {
                $scope = PivotIdNormalizer::normalize($ids);
            }

            $before   = $this->snapshot($scope);
            $toDetach = array_keys($before);

            if ([] === $toDetach) {
                return 0;
            }

            $count = $this->relation->detach($toDetach, $touch);

            $this->record(
                'detached',
                $before,
                sprintf('Detached %d %s', $count, $this->singular()),
                $scope,
            );

            return $count;
        });
    }

    /**
     * Write the entries of this call in another log than the configured one.
     */
    public function inLog(string $logName): static
    {
        $this->logName = $logName;

        return $this;
    }

    /**
     * Sync the pivot table with activity logging.
     *
     * The IDs can carry their own pivot attributes, as in Laravel. Nothing is logged when nothing changed.
     *
     * @param array<int|string, mixed>|Collection<int, Model>|Model $ids
     *
     * @return array{attached: array<int, int|string>, detached: array<int, int|string>, updated: array<int, int|string>}
     */
    public function sync(array|Collection|Model $ids, bool $detaching = true): array
    {
        return $this->atomic(function () use ($ids, $detaching): array {
            $scope = null;

            if (! $detaching) {
                $scope = PivotIdNormalizer::normalize($ids);
            }

            $before = $this->snapshot($scope);

            /** @var array{attached: array<int, int|string>, detached: array<int, int|string>, updated: array<int, int|string>} $result */
            $result = $this->relation->sync($ids, $detaching);

            $this->record(
                'synced',
                $before,
                sprintf('Synced %s (%s)', $this->relation->getRelationName(), PivotSyncSummary::fromResult($result)),
                $scope,
            );

            return $result;
        });
    }

    /**
     * Sync without detaching with activity logging.
     *
     * @param array<int|string, mixed>|Collection<int, Model>|Model $ids
     *
     * @return array{attached: array<int, int|string>, detached: array<int, int|string>, updated: array<int, int|string>}
     */
    public function syncWithoutDetaching(array|Collection|Model $ids): array
    {
        return $this->atomic(function () use ($ids): array {
            $scope  = PivotIdNormalizer::normalize($ids);
            $before = $this->snapshot($scope);

            /** @var array{attached: array<int, int|string>, detached: array<int, int|string>, updated: array<int, int|string>} $result */
            $result = $this->relation->syncWithoutDetaching($ids);

            $this->record(
                'synced_without_detaching',
                $before,
                sprintf('Added %d %s without detaching', count($result['attached']), $this->singular()),
                $scope,
            );

            return $result;
        });
    }

    /**
     * Toggle models in the pivot table with activity logging.
     *
     * Attaches IDs that are not present, detaches IDs that are.
     *
     * @param array<int|string, mixed>|Collection<int, Model>|Model|int|string $ids   IDs to toggle
     * @param bool                                                             $touch Whether to touch parent timestamps
     *
     * @return array{attached: array<int, int|string>, detached: array<int, int|string>}
     */
    public function toggle(mixed $ids, bool $touch = true): array
    {
        return $this->atomic(function () use ($ids, $touch): array {
            $scope  = PivotIdNormalizer::normalize($ids);
            $before = $this->snapshot($scope);

            /** @var array{attached: array<int, int|string>, detached: array<int, int|string>} $result */
            $result = $this->relation->toggle($ids, $touch);

            $this->record(
                'toggled',
                $before,
                sprintf(
                    'Toggled %s (attached %d, detached %d)',
                    $this->relation->getRelationName(),
                    count($result['attached']),
                    count($result['detached']),
                ),
                $scope,
            );

            return $result;
        });
    }

    /**
     * Update existing pivot attributes with activity logging.
     *
     * @param array<int|string>|int|string $ids
     * @param array<string, mixed>         $attributes
     */
    public function updateExistingPivot(array|int|string $ids, array $attributes, bool $touch = true): int
    {
        return $this->atomic(function () use ($ids, $attributes, $touch): int {
            $scope  = PivotIdNormalizer::normalize($ids);
            $before = $this->snapshot($scope);
            $count  = 0;

            foreach ($scope as $id) {
                $count += $this->relation->updateExistingPivot($id, $attributes, $touch);
            }

            $this->record(
                'updated_pivot',
                $before,
                sprintf('Updated %d %s pivot attributes', $count, $this->singular()),
                $scope,
            );

            return $count;
        });
    }

    /**
     * Same as inLog(), under the name of Spatie.
     */
    public function useLog(string $logName): static
    {
        return $this->inLog($logName);
    }

    /**
     * Set a custom log message.
     */
    public function withMessage(string $message): static
    {
        $this->logMessage = $message;

        return $this;
    }

    /**
     * Leave columns of the pivot table out of the log, on top of the hidden ones of the configuration.
     *
     * @param array<int, string> $columns
     */
    public function withoutColumns(array $columns): static
    {
        $this->hiddenColumns = [...$this->hiddenColumns, ...$columns];

        return $this;
    }

    /**
     * Run the operation and its log together: when the log cannot be written, the operation is rolled back.
     *
     * @template TReturn
     *
     * @param Closure(): TReturn $callback
     *
     * @return TReturn
     */
    protected function atomic(Closure $callback): mixed
    {
        try {
            return $this->relation->getParent()->getConnection()->transaction($callback);
        }
        finally {
            // The message is for one operation, logged or not: it must not leak into the next one
            $this->logMessage = null;
        }
    }

    /**
     * Write the activity of an operation, from the rows of the pivot table before it and now.
     *
     * Nothing is written when no row changed.
     *
     * @param array<int|string, array<string, mixed>> $before
     * @param array<int, int|string>|null             $ids    The related IDs the operation is about, all when null
     */
    protected function record(string $operation, array $before, string $defaultMessage, ?array $ids = null): void
    {
        // Spatie can be disabled (activitylog.enabled, withoutLogging): the operation runs, nothing is written
        if (resolve(ActivityLogStatus::class)->disabled()) {
            return;
        }

        $pivotDiff = new PivotDiff($before, $this->snapshot($ids));

        if ($pivotDiff->isEmpty()) {
            return;
        }

        $activityLogger = $this->logger()
            ->performedOn($this->relation->getParent())
            ->event($operation);

        $this->applyCauser($activityLogger);

        $activityLogger->withProperties([
            'relation'     => $this->relation->getRelationName(),
            'table'        => $this->relation->getTable(),
            'related_type' => $this->relation->getRelated()->getMorphClass(),
        ])
            ->withChanges([
                'old'        => $pivotDiff->old(),
                'attributes' => $pivotDiff->new(),
            ])
            ->log($this->logMessage ?? $defaultMessage);
    }

    /**
     * @param array<int, int|string>|null $ids
     *
     * @return array<int|string, array<string, mixed>>
     */
    protected function snapshot(?array $ids): array
    {
        return PivotSnapshot::take($this->relation, PivotColumns::hidden($this->relation, $this->hiddenColumns), $ids);
    }

    /**
     * Priority: anonymous flag, then the explicit causer, then the default of Spatie (the authenticated user).
     */
    private function applyCauser(ActivityLogger $activityLogger): void
    {
        if ($this->useAnonymousCauser) {
            $activityLogger->causedByAnonymous();

            return;
        }

        $activityLogger->causedBy($this->causedBy);
    }

    private function logger(): ActivityLogger
    {
        $logName = $this->logName ?? config('laravel-activity.log_name');

        if (is_string($logName)) {
            return activity($logName);
        }

        return activity();
    }

    private function singular(): string
    {
        return (string) str($this->relation->getRelationName())->singular()->lower();
    }
}
