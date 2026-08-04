<?php

declare(strict_types=1);

namespace Zairakai\LaravelActivity\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use LogicException;
use Spatie\Activitylog\Support\ActivityLogger;

/**
 * Fluent API for pivot table activity logging.
 *
 * @template TRelatedModel of Model
 * @template TParentModel of Model
 */
class PivotActivityFluent
{
    protected ?Model $causedBy = null;

    protected ?string $logMessage = null;

    protected bool $useAnonymousCauser = false;

    /**
     * @param BelongsToMany<TRelatedModel, TParentModel> $relation
     */
    public function __construct(protected BelongsToMany $relation) {}

    /**
     * Attach models to the pivot table with activity logging.
     *
     * @param array<int|string>|Collection<int, Model>|Model|int|string $ids
     * @param array<string, mixed>                                      $attributes
     */
    public function attach(mixed $ids, array $attributes = [], bool $touch = true): void
    {
        $beforeIds   = $this->getCurrentPivotIds();
        $idsToAttach = $this->normalizeIds($ids);

        // Only attach new IDs
        $newIds = array_diff($idsToAttach, $beforeIds);

        if ([] === $newIds) {
            return;
        }

        $this->relation->attach($newIds, $attributes, $touch);

        $afterIds = $this->getCurrentPivotIds();

        $this->logActivity(
            operation: 'attached',
            before: $beforeIds,
            after: $afterIds,
            defaultMessage: sprintf(
                'Attached %d %s',
                count($newIds),
                str($this->relation->getRelationName())->singular()->lower(),
            ),
        );
    }

    /**
     * Set the user or model causing this activity.
     */
    public function by(?Model $model = null): static
    {
        if ($model instanceof Model) {
            $this->causedBy = $model;

            return $this;
        }

        /** @var (Authenticatable&Model)|null $authenticatedUser */
        $authenticatedUser = Auth::user();

        $this->causedBy = $authenticatedUser;

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
     * Detach models from the pivot table with activity logging.
     *
     * @param array<int|string>|Collection<int, Model>|Model|int|string|null $ids   IDs to detach, or null to detach all
     * @param bool                                                           $touch Whether to touch parent timestamps
     *
     * @return int Number of detached records
     */
    public function detach(mixed $ids = null, bool $touch = true): int
    {
        $beforeIds = $this->getCurrentPivotIds();

        $idsToDetach = null === $ids ? $beforeIds : array_intersect($this->normalizeIds($ids), $beforeIds);

        if ([] === $idsToDetach) {
            return 0;
        }

        $count = $this->relation->detach($idsToDetach, $touch);

        $afterIds = $this->getCurrentPivotIds();

        $this->logActivity(
            operation: 'detached',
            before: $beforeIds,
            after: $afterIds,
            defaultMessage: sprintf(
                'Detached %d %s',
                $count,
                str($this->relation->getRelationName())->singular()->lower(),
            ),
        );

        return $count;
    }

    /**
     * Sync the pivot table with activity logging.
     *
     * @param array<int|string>|Collection<int, Model>|Model $ids
     *
     * @return array<string, array<int, int|string>>
     */
    public function sync(array|Collection|Model $ids, bool $detaching = true): array
    {
        $beforeIds = $this->getCurrentPivotIds();
        $newIds    = $this->normalizeIds($ids);

        // Check if anything actually changed
        if (! $this->hasChanged($beforeIds, $newIds)) {
            return ['attached' => [], 'detached' => [], 'updated' => []];
        }

        /** @var array{attached: array<int, int|string>, detached: array<int, int|string>, updated: array<int, int|string>} $result */
        $result   = $this->relation->sync($ids, $detaching);
        $afterIds = $this->getCurrentPivotIds();

        $summary = PivotSyncSummary::fromResult($result);

        $this->logActivity(
            operation: 'synced',
            before: $beforeIds,
            after: $afterIds,
            defaultMessage: sprintf(
                'Synced %s (%s)',
                $this->relation->getRelationName(),
                $summary,
            ),
        );

        return $result;
    }

    /**
     * Sync without detaching with activity logging.
     *
     * @param array<int|string>|Collection<int, Model>|Model $ids
     *
     * @return array<string, array<int, int|string>>
     */
    public function syncWithoutDetaching(array|Collection|Model $ids): array
    {
        $beforeIds = $this->getCurrentPivotIds();
        $newIds    = $this->normalizeIds($ids);

        // Only log new additions
        $addedIds = array_diff($newIds, $beforeIds);

        if ([] === $addedIds) {
            return ['attached' => [], 'detached' => [], 'updated' => []];
        }

        /** @var array{attached: array<int, int|string>, detached: array<int, int|string>, updated: array<int, int|string>} $result */
        $result   = $this->relation->syncWithoutDetaching($ids);
        $afterIds = $this->getCurrentPivotIds();

        $this->logActivity(
            operation: 'synced_without_detaching',
            before: $beforeIds,
            after: $afterIds,
            defaultMessage: sprintf(
                'Added %d %s without detaching',
                count($addedIds),
                str($this->relation->getRelationName())->singular()->lower(),
            ),
        );

        return $result;
    }

    /**
     * Toggle models in the pivot table with activity logging.
     *
     * Attaches IDs that are not present, detaches IDs that are.
     *
     * @param array<int|string>|Collection<int, Model>|Model|int|string $ids   IDs to toggle
     * @param bool                                                      $touch Whether to touch parent timestamps
     *
     * @return array<string, array<int, int|string>> Result with 'attached' and 'detached' keys
     */
    public function toggle(mixed $ids, bool $touch = true): array
    {
        $beforeIds = $this->getCurrentPivotIds();

        /** @var array{attached: array<int, int|string>, detached: array<int, int|string>} $result */
        $result   = $this->relation->toggle($ids, $touch);
        $afterIds = $this->getCurrentPivotIds();

        $attachedCount = count($result['attached']);
        $detachedCount = count($result['detached']);

        $this->logActivity(
            operation: 'toggled',
            before: $beforeIds,
            after: $afterIds,
            defaultMessage: sprintf(
                'Toggled %s (attached %d, detached %d)',
                $this->relation->getRelationName(),
                $attachedCount,
                $detachedCount,
            ),
        );

        return $result;
    }

    /**
     * Update existing pivot attributes with activity logging.
     *
     * @param array<int|string>|int|string $ids
     * @param array<string, mixed>         $attributes
     */
    public function updateExistingPivot(array|int|string $ids, array $attributes, bool $touch = true): int
    {
        $normalizedIds    = $this->normalizeIds($ids);
        $beforeAttributes = $this->getPivotAttributes($normalizedIds);

        $count = 0;

        foreach ($normalizedIds as $normalizedId) {
            $count += $this->relation->updateExistingPivot($normalizedId, $attributes, $touch);
        }

        $afterAttributes = $this->getPivotAttributes($normalizedIds);

        $this->logActivity(
            operation: 'updated_pivot',
            before: $beforeAttributes,
            after: $afterAttributes,
            defaultMessage: sprintf(
                'Updated %d %s pivot attributes',
                $count,
                str($this->relation->getRelationName())->singular()->lower(),
            ),
        );

        return $count;
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
     * @return array<int, int|string>
     */
    protected function getCurrentPivotIds(): array
    {
        return $this->relation
            ->get()
            ->pluck($this->relation->getRelatedKeyName())
            ->map(fn ($id): int|string => $this->extractKey($id))
            ->values()
            ->all();
    }

    /**
     * @param array<int, int|string> $ids
     *
     * @return array<int|string, array<string, mixed>>
     */
    protected function getPivotAttributes(array $ids): array
    {
        $attributes = [];
        $keyName    = $this->relation->getRelatedKeyName();

        /** @var Collection<int, Model> $models */
        $models = $this->relation
            ->whereIn($keyName, $ids)
            ->get();

        $models->each(function (Model $model) use ($keyName, &$attributes): void {
            $modelKey = $model->{$keyName};
            assert(is_int($modelKey) || is_string($modelKey));

            $pivot                 = $model->pivot;
            $attributes[$modelKey] = $pivot instanceof Model ? $pivot->getAttributes() : [];
        });

        return $attributes;
    }

    /**
     * @param array<int, int|string> $before
     * @param array<int, int|string> $after
     */
    protected function hasChanged(array $before, array $after): bool
    {
        sort($before);
        sort($after);

        return $before !== $after;
    }

    /**
     * Write an activity log entry for the current operation.
     *
     * Applies causer resolution, attaches before/after state as properties,
     * and resets per-operation state (message, anonymous flag) after logging.
     *
     * @param string $operation      Operation name (attached, detached, synced, …)
     * @param mixed  $before         State before the operation
     * @param mixed  $after          State after the operation
     * @param string $defaultMessage Fallback message when no custom message is set
     */
    protected function logActivity(
        string $operation,
        mixed $before,
        mixed $after,
        string $defaultMessage,
    ): void {
        $model = $this->relation->getParent();

        $activityLogger = activity()
            ->performedOn($model);

        $this->applyCauser($activityLogger, $model);

        $activityLogger->withProperties([
            'operation'  => $operation,
            'relation'   => $this->relation->getRelationName(),
            'attributes' => $after,
            'old'        => $before,
        ])
            ->log($this->logMessage ?? $defaultMessage);

        // Reset state for next operation
        $this->logMessage         = null;
        $this->useAnonymousCauser = false;
    }

    /**
     * @return array<int, int|string>
     */
    protected function normalizeIds(mixed $ids): array
    {
        return PivotIdNormalizer::normalize($ids);
    }

    /**
     * Resolve and apply the causer to the activity logger.
     *
     * Priority: anonymous flag > explicit causedBy > parent model fallback.
     */
    private function applyCauser(ActivityLogger $activityLogger, Model $model): void
    {
        if ($this->useAnonymousCauser) {
            $activityLogger->causedByAnonymous();

            return;
        }

        $activityLogger->causedBy($this->causedBy ?? $model);
    }

    /**
     * Extract a scalar key from a value or Model instance.
     *
     * @throws LogicException When the resolved key is neither int nor string
     */
    private function extractKey(mixed $value): int|string
    {
        $key = $value instanceof Model
            ? $value->getKey()
            : $value;

        if (is_int($key) || is_string($key)) {
            return $key;
        }

        throw new LogicException('Pivot key must be int or string');
    }
}
