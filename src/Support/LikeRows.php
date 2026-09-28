<?php

declare(strict_types=1);

namespace RoundlyConsulting\Likes\Support;

use Closure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use RoundlyConsulting\Likes\DataTransferObjects\LikeData;
use RoundlyConsulting\Likes\Models\Like;

/**
 * Race-safe row writes shared by the like actions.
 *
 * The likes table holds one row per actor + likeable + reaction type — a unique index that
 * covers soft-deleted rows too — and a like is switched on and off through `deleted_at`.
 * Two requests can still land between a read and a write (a double-click, a second tab, a
 * client retry), so every write here is guarded by the database rather than by the read:
 *
 *  - a new row goes in through `createOrFirst()`: the unique index refuses the losing
 *    INSERT and that request adopts the winner's row instead (inside a savepoint when a
 *    transaction is open, so Postgres does not abort the caller's transaction);
 *  - restoring or removing an existing row re-reads it under `lockForUpdate()` inside a
 *    transaction and writes only if it is still in the state the caller saw.
 *
 * Every write returns the row only when *this* call changed it, so the calling action fires
 * its event exactly once per real change.
 *
 * @internal
 */
final class LikeRows
{
    /**
     * Make the actor's like of this type active. Returns the row when this call created or
     * restored it, null when it was already active.
     */
    public static function activate(LikeData $data): ?Like
    {
        $model = LikeModel::class();
        $key = self::key($data->actor, $data->likeable, $data->type);

        /** @var Like|null $row */
        $row = $model::withTrashed()->where($key)->first();

        if ($row === null) {
            /** @var Like $row */
            $row = $model::withTrashed()->createOrFirst($key);

            if ($row->wasRecentlyCreated) {
                return $row;
            }
        }

        return $row->trashed() ? self::restore($row) : null;
    }

    /**
     * The actor's active like of this type, if any.
     */
    public static function active(LikeData $data): ?Like
    {
        $model = LikeModel::class();

        /** @var Like|null $row */
        $row = $model::query()->where(self::key($data->actor, $data->likeable, $data->type))->first();

        return $row;
    }

    /**
     * Soft-delete an active row. Returns it when this call removed it, null when another
     * request removed it first.
     */
    public static function remove(Like $row): ?Like
    {
        return self::whileLocked($row, static function (Like $locked): ?Like {
            if ($locked->trashed() || $locked->delete() === false) {
                return null;
            }

            return $locked;
        });
    }

    /**
     * Whether the actor holds any row — active or soft-deleted — on the likeable.
     */
    public static function exists(Model $actor, Model $likeable): bool
    {
        $model = LikeModel::class();

        return $model::withTrashed()->where(self::key($actor, $likeable))->exists();
    }

    /**
     * Every row the actor holds on the likeable (at most one per type, soft-deleted ones
     * included), oldest first and locked for update. Call it inside a transaction.
     *
     * @return Collection<int, Like>
     */
    public static function lockAll(Model $actor, Model $likeable): Collection
    {
        $model = LikeModel::class();

        /** @var Collection<int, Like> $rows */
        $rows = $model::withTrashed()
            ->where(self::key($actor, $likeable))
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        return $rows;
    }

    /**
     * Switch a row's reaction type in place. Returns false — leaving the row untouched — when
     * a row of the target type appeared since it was read and the unique index refused the
     * switch. Runs in a savepoint so a refusal does not abort the surrounding transaction.
     */
    public static function retype(Like $row, string $type): bool
    {
        $from = $row->type;

        try {
            return $row->getConnection()->transaction(static fn (): bool => $row->update(['type' => $type]));
        } catch (UniqueConstraintViolationException) {
            $row->setAttribute('type', $from);
            $row->syncOriginalAttribute('type');

            return false;
        }
    }

    /**
     * Restore a soft-deleted row. Returns it when this call restored it, null when another
     * request restored it first.
     */
    private static function restore(Like $row): ?Like
    {
        return self::whileLocked($row, static function (Like $locked): ?Like {
            if (! $locked->trashed() || $locked->restore() === false) {
                return null;
            }

            return $locked;
        });
    }

    /**
     * Re-read the row under a row lock and hand the fresh copy to the write.
     *
     * @param  Closure(Like): ?Like  $write
     */
    private static function whileLocked(Like $row, Closure $write): ?Like
    {
        return $row->getConnection()->transaction(static function () use ($row, $write): ?Like {
            /** @var Like|null $locked */
            $locked = $row->newQueryWithoutScopes()->whereKey($row->getKey())->lockForUpdate()->first();

            return $locked === null ? null : $write($locked);
        });
    }

    /**
     * The columns of the unique index, as a where/create attribute map.
     *
     * @return array<string, mixed>
     */
    private static function key(Model $actor, Model $likeable, ?string $type = null): array
    {
        $key = [
            'actor_type' => $actor->getMorphClass(),
            'actor_id' => $actor->getKey(),
            'likeable_type' => $likeable->getMorphClass(),
            'likeable_id' => $likeable->getKey(),
        ];

        return $type === null ? $key : [...$key, 'type' => $type];
    }
}
