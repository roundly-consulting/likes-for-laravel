<?php

declare(strict_types=1);

namespace RoundlyConsulting\Likes\Actions;

use RoundlyConsulting\Likes\DataTransferObjects\LikeData;
use RoundlyConsulting\Likes\Events\Liked;
use RoundlyConsulting\Likes\Events\ReactionChanged;
use RoundlyConsulting\Likes\Events\Unliked;
use RoundlyConsulting\Likes\Models\Like;
use RoundlyConsulting\Likes\Support\LikeModel;
use RoundlyConsulting\Likes\Support\LikeRows;

/**
 * Reacts to a likeable while keeping exactly one active reaction per actor + likeable.
 * Unlike like(), which lets multiple typed reactions co-exist, react() collapses to a single
 * active row:
 *
 *  - no reaction yet → a fresh like (`Liked`);
 *  - the requested type is already active → every other active reaction is removed
 *    (`Unliked` each);
 *  - another type is active → the newest one switches to the requested type (`ReactionChanged`)
 *    — in place, or by restoring the requested type's own soft-deleted row, since the table
 *    holds one row per type — and any further active reactions are removed (`Unliked` each);
 *  - only removed reactions → the requested type's row (or the newest removed row, switched
 *    to the requested type) is restored (`Liked`).
 *
 * The rows are read and rewritten under a row lock in one transaction, and the events fire
 * after it commits, so two concurrent reacts cannot both report the same change.
 */
final class SwitchReactionAction
{
    /**
     * @return bool true — a reaction now exists
     */
    public function execute(LikeData $data): bool
    {
        // Nothing to switch yet: the first reaction is a plain, race-safe insert. Kept out of
        // the locked transaction because a locking read that finds no row takes a gap lock on
        // MySQL, and two such inserters deadlock each other.
        if (! LikeRows::exists($data->actor, $data->likeable)) {
            $like = LikeRows::activate($data);

            if ($like !== null) {
                Liked::dispatch($data->actor, $data->likeable, $data->type, $like);
            }
        }

        $model = LikeModel::class();

        /** @var list<Liked|Unliked|ReactionChanged> $events */
        $events = (new $model)->getConnection()->transaction(fn (): array => $this->collapse($data));

        foreach ($events as $event) {
            event($event);
        }

        return true;
    }

    /**
     * Bring the locked rows to exactly one active reaction of the requested type and return
     * the events that describe the change.
     *
     * @return list<Liked|Unliked|ReactionChanged>
     */
    private function collapse(LikeData $data, bool $retried = false): array
    {
        $rows = LikeRows::lockAll($data->actor, $data->likeable);

        $target = $rows->firstWhere('type', $data->type);

        $others = $rows
            ->reject(static fn (Like $row): bool => $row->trashed() || $row->type === $data->type)
            ->sortByDesc('id')
            ->values();

        if ($target !== null && ! $target->trashed()) {
            return $this->remove($data, $others);
        }

        $from = $others->shift();

        if ($from === null) {
            // Only removed reactions: bring the requested one back, reusing a removed row.
            $reuse = $target ?? $rows->sortByDesc('id')->first();

            if ($reuse === null) {
                // Every row vanished under us (a host force-deleted them): start afresh.
                $like = LikeRows::activate($data);

                return $like === null ? [] : [new Liked($data->actor, $data->likeable, $data->type, $like)];
            }

            if ($reuse->type !== $data->type && ! LikeRows::retype($reuse, $data->type)) {
                return $retried ? [] : $this->collapse($data, retried: true);
            }

            $reuse->restore();

            return [new Liked($data->actor, $data->likeable, $data->type, $reuse)];
        }

        $previous = $from->type;

        if ($target !== null) {
            // The requested type has its own (soft-deleted) row; the unique index forbids
            // renaming another row onto it, so restore it and retire the old reaction.
            $target->restore();
            $from->delete();
            $like = $target;
        } elseif (LikeRows::retype($from, $data->type)) {
            $like = $from;
        } else {
            // A concurrent request created the requested type after the rows were locked:
            // adopt it and retire the rest instead of failing.
            return $retried ? [] : $this->collapse($data, retried: true);
        }

        return [
            new ReactionChanged($data->actor, $data->likeable, $previous, $data->type, $like),
            ...$this->remove($data, $others),
        ];
    }

    /**
     * @param  iterable<Like>  $rows
     * @return list<Unliked>
     */
    private function remove(LikeData $data, iterable $rows): array
    {
        $events = [];

        foreach ($rows as $row) {
            $row->delete();

            $events[] = new Unliked($data->actor, $data->likeable, $row->type, $row);
        }

        return $events;
    }
}
