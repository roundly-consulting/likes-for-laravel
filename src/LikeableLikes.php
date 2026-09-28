<?php

declare(strict_types=1);

namespace RoundlyConsulting\Likes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Likes\DataTransferObjects\ReactionSummary;
use RoundlyConsulting\Likes\Models\Like;
use RoundlyConsulting\Likes\Support\ActorResolver;
use RoundlyConsulting\Likes\Support\LikeModel;
use RoundlyConsulting\Likes\Support\ReactionType;

/**
 * The read side of one likeable — `Likes::for($post)`. Every query is scoped to exactly that
 * model (morph type and key), so a same-id row of another type never counts.
 */
final readonly class LikeableLikes
{
    public function __construct(
        public Model $likeable,
    ) {}

    /**
     * Number of active likes, optionally of one reaction type. Uses an eager-loaded count
     * when present — `likes_count` from `withLikesCount()`, or `likes_{type}_count` from
     * `withLikesCount($type)` for a typed count — and counts live otherwise.
     */
    public function count(?string $type = null): int
    {
        // Read the raw attribute bag: getAttribute() on an unloaded key throws under
        // Model::preventAccessingMissingAttributes() (strict mode).
        $eager = $this->likeable->getAttributes()[ReactionType::countAttribute($type)] ?? null;

        if (is_numeric($eager)) {
            return (int) $eager;
        }

        $query = $this->likes();

        if ($type !== null) {
            $query->where('type', ReactionType::resolve($type));
        }

        return $query->count();
    }

    /**
     * Per-type reaction breakdown in (at most) two queries: a grouped count plus the viewer's
     * current reaction. The viewer defaults to the resolved actor; guests get no reaction.
     */
    public function summary(?Model $viewer = null): ReactionSummary
    {
        /** @var array<int, object{type: string, aggregate: int}> $rows */
        $rows = $this->likes()
            ->getQuery()
            ->whereNull('deleted_at')
            ->groupBy('type')
            ->selectRaw('type, count(*) as aggregate')
            ->get()
            ->all();

        $counts = [];

        foreach ($rows as $row) {
            $counts[(string) $row->type] = (int) $row->aggregate;
        }

        return new ReactionSummary($counts, array_sum($counts), $this->topReaction($counts), $this->viewerReaction($viewer));
    }

    /**
     * Whether the actor actively likes the model with the given (or default) reaction type.
     */
    public function likedBy(Model $actor, ?string $type = null): bool
    {
        return $this->likes()
            ->whereMorphedTo('actor', $actor)
            ->where('type', ReactionType::resolve($type))
            ->exists();
    }

    /** @return Builder<Like> */
    private function likes(): Builder
    {
        $model = LikeModel::class();

        return $model::query()->whereMorphedTo('likeable', $this->likeable);
    }

    /**
     * @param  array<string, int>  $counts
     */
    private function topReaction(array $counts): ?string
    {
        if ($counts === []) {
            return null;
        }

        $top = null;
        $best = -1;

        // Iterate in config order so ties resolve to the earliest configured type.
        foreach (ReactionType::allowed() as $type) {
            $count = $counts[$type] ?? 0;

            if ($count > $best) {
                $best = $count;
                $top = $type;
            }
        }

        // Fall back to whatever is present if no configured type matched.
        if ($best <= 0) {
            $top = array_key_first($counts);
        }

        return $top;
    }

    private function viewerReaction(?Model $viewer): ?string
    {
        $viewer = ActorResolver::resolve($viewer);

        if (! $viewer instanceof Model) {
            return null;
        }

        $query = $this->likes()
            ->getQuery()
            ->whereNull('deleted_at')
            ->where('actor_id', $viewer->getKey())
            ->where('actor_type', $viewer->getMorphClass());

        // Several active reactions: the first configured one wins, never an arbitrary row.
        ReactionType::orderByPreference($query);

        $type = $query->value('type');

        return is_string($type) ? $type : null;
    }
}
