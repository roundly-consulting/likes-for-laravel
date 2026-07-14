<?php

declare(strict_types=1);

namespace RoundlyConsulting\Likes\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Query\Expression;
use RoundlyConsulting\Likes\DataTransferObjects\ReactionSummary;
use RoundlyConsulting\Likes\Models\Like;
use RoundlyConsulting\Likes\Support\ActorResolver;
use RoundlyConsulting\Likes\Support\LikeModel;
use RoundlyConsulting\Likes\Support\ReactionType;
use RoundlyConsulting\Likes\Support\TrendingScore;

/**
 * @phpstan-require-extends Model
 */
trait HasLikes
{
    /**
     * @return MorphMany<Like, $this>
     */
    public function likes(): MorphMany
    {
        $model = LikeModel::class();

        return $this->morphMany($model, 'likeable');
    }

    public function hasBeenLikedBy(Model $actor, ?string $type = null): bool
    {
        return $this->likes()
            ->whereMorphedTo('actor', $actor)
            ->where('type', ReactionType::resolve($type))
            ->exists();
    }

    /**
     * Readable alias of hasBeenLikedBy().
     */
    public function isLikedBy(Model $actor, ?string $type = null): bool
    {
        return $this->hasBeenLikedBy($actor, $type);
    }

    /**
     * Number of likes for this model. Uses the eager-loaded "likes_count"
     * value when present (e.g. via withLikesCount()), otherwise runs a live
     * count. When a type is given, an unscoped eager-loaded count is ignored.
     */
    public function likesCount(?string $type = null): int
    {
        if ($type === null && $this->getAttribute('likes_count') !== null) {
            return (int) $this->getAttribute('likes_count');
        }

        $query = $this->likes();

        if ($type !== null) {
            $query->where('type', ReactionType::resolve($type));
        }

        return $query->count();
    }

    /**
     * Eager-load the likes count into a "likes_count" attribute.
     *
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public function scopeWithLikesCount(Builder $query, ?string $type = null): Builder
    {
        return $query->withCount([
            'likes' => function (Builder $likes) use ($type): void {
                $this->filterByType($likes, $type);
            },
        ]);
    }

    /**
     * Order by like count, least liked first.
     *
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public function scopeOrderByLikes(Builder $query, ?string $type = null): Builder
    {
        return $this->scopeWithLikesCount($query, $type)->orderBy('likes_count');
    }

    /**
     * Order by like count, most liked first.
     *
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public function scopeOrderByLikesDesc(Builder $query, ?string $type = null): Builder
    {
        return $this->scopeWithLikesCount($query, $type)->orderByDesc('likes_count');
    }

    /**
     * Restrict to models the actor has liked.
     *
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public function scopeWhereLikedBy(Builder $query, Model $actor, ?string $type = null): Builder
    {
        return $query->whereHas('likes', function (Builder $likes) use ($actor, $type): void {
            $likes->whereMorphedTo('actor', $actor);
            $this->filterByType($likes, $type);
        });
    }

    /**
     * Restrict to models the actor has not liked.
     *
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public function scopeWhereNotLikedBy(Builder $query, Model $actor, ?string $type = null): Builder
    {
        return $query->whereDoesntHave('likes', function (Builder $likes) use ($actor, $type): void {
            $likes->whereMorphedTo('actor', $actor);
            $this->filterByType($likes, $type);
        });
    }

    /**
     * Hydrate per-row viewer state for an entire feed page in a single query:
     * an "is_liked" boolean and the viewer's "liked_reaction" type. The actor
     * defaults to the resolved auth actor; for guests it renders is_liked=false
     * and liked_reaction=null rather than throwing.
     *
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public function scopeWithLikedState(Builder $query, ?Model $actor = null, ?string $type = null): Builder
    {
        // Mirror withCount(): keep the base "*" so explicit selects still work.
        if ($query->getQuery()->columns === null || $query->getQuery()->columns === []) {
            $query->select($query->getModel()->getTable().'.*');
        }

        $actor = ActorResolver::resolve($actor);

        if (! $actor instanceof Model) {
            return $query
                ->addSelect(new Expression('0 as is_liked'))
                ->addSelect(new Expression('null as liked_reaction'));
        }

        $table = $this->likesTable();
        $morphClass = $this->getMorphClass();
        $resolvedType = $type !== null ? ReactionType::resolve($type) : null;

        $correlate = function (QueryBuilder $sub, Expression $select) use ($table, $morphClass, $actor, $resolvedType): void {
            $sub->select($select)
                ->from($table)
                ->whereColumn($table.'.likeable_id', $this->qualifyColumn($this->getKeyName()))
                ->where($table.'.likeable_type', $morphClass)
                ->where($table.'.actor_id', $actor->getKey())
                ->where($table.'.actor_type', $actor->getMorphClass())
                ->whereNull($table.'.deleted_at')
                ->limit(1);

            if ($resolvedType !== null) {
                $sub->where($table.'.type', $resolvedType);
            }
        };

        return $query
            ->selectSub(
                fn (QueryBuilder $sub) => $correlate($sub, new Expression('count(*)')),
                'is_liked',
            )
            ->selectSub(
                fn (QueryBuilder $sub) => $correlate($sub, new Expression('type')),
                'liked_reaction',
            );
    }

    /**
     * Rank by a weighted sum of reactions (config "likes.weights"). With the
     * default all-1 weights this is equivalent to ranking by raw like count.
     *
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public function scopeOrderByLikeScore(Builder $query, string $direction = 'desc', ?string $type = null): Builder
    {
        $query = $this->withScoreSelect($query, $type);

        return $query->orderBy('like_score', $this->direction($direction));
    }

    /**
     * Rank by a recency-weighted trending score: a weighted all-time score plus
     * a boosted weighted count of recent likes (config "likes.trending").
     *
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public function scopeOrderByTrending(Builder $query, string $direction = 'desc', ?string $type = null): Builder
    {
        if ($query->getQuery()->columns === null || $query->getQuery()->columns === []) {
            $query->select($query->getModel()->getTable().'.*');
        }

        $trending = TrendingScore::portableTrending();
        $table = $this->likesTable();
        $resolvedType = $type !== null ? ReactionType::resolve($type) : null;

        $query->selectSub(function (QueryBuilder $sub) use ($trending, $table, $resolvedType): void {
            $sub->select(new Expression('coalesce('.$trending['expression'].', 0)'))
                ->addBinding($trending['bindings'], 'select')
                ->from($table)
                ->whereColumn($table.'.likeable_id', $this->qualifyColumn($this->getKeyName()))
                ->where($table.'.likeable_type', $this->getMorphClass())
                ->whereNull($table.'.deleted_at');

            if ($resolvedType !== null) {
                $sub->where($table.'.type', $resolvedType);
            }
        }, 'trending_score');

        return $query->orderBy('trending_score', $this->direction($direction));
    }

    /**
     * Per-type reaction breakdown for this model in (at most) two queries: a
     * grouped count plus the viewer's current reaction.
     */
    public function reactionSummary(?Model $viewer = null): ReactionSummary
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

        $total = array_sum($counts);
        $top = $this->topReaction($counts);
        $viewerReaction = $this->viewerReaction($viewer);

        return new ReactionSummary($counts, $total, $top, $viewerReaction);
    }

    /**
     * Compact array shape for API responses: count, the viewer's state, and the
     * per-type breakdown. Reuses reactionSummary() so counting logic is shared.
     *
     * @return array{count: int, viewer_state: array{liked: bool, reaction: ?string}, breakdown: array<string, int>}
     */
    public function toLikeArray(?Model $viewer = null): array
    {
        $summary = $this->reactionSummary($viewer);

        return [
            'count' => $summary->total,
            'viewer_state' => [
                'liked' => $summary->viewerReaction !== null,
                'reaction' => $summary->viewerReaction,
            ],
            'breakdown' => $summary->counts,
        ];
    }

    /**
     * Add the weighted "like_score" select used by orderByLikeScore().
     *
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    private function withScoreSelect(Builder $query, ?string $type): Builder
    {
        if ($query->getQuery()->columns === null || $query->getQuery()->columns === []) {
            $query->select($query->getModel()->getTable().'.*');
        }

        $score = TrendingScore::weightedSum();
        $table = $this->likesTable();
        $resolvedType = $type !== null ? ReactionType::resolve($type) : null;

        return $query->selectSub(function (QueryBuilder $sub) use ($score, $table, $resolvedType): void {
            $sub->select(new Expression('coalesce('.$score['expression'].', 0)'))
                ->addBinding($score['bindings'], 'select')
                ->from($table)
                ->whereColumn($table.'.likeable_id', $this->qualifyColumn($this->getKeyName()))
                ->where($table.'.likeable_type', $this->getMorphClass())
                ->whereNull($table.'.deleted_at');

            if ($resolvedType !== null) {
                $sub->where($table.'.type', $resolvedType);
            }
        }, 'like_score');
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

        $type = $this->likes()
            ->getQuery()
            ->whereNull('deleted_at')
            ->where('actor_id', $viewer->getKey())
            ->where('actor_type', $viewer->getMorphClass())
            ->value('type');

        return is_string($type) ? $type : null;
    }

    private function likesTable(): string
    {
        $table = config('likes.table', 'likes');

        return is_string($table) ? $table : 'likes';
    }

    /**
     * @return 'asc'|'desc'
     */
    private function direction(string $direction): string
    {
        return strtolower($direction) === 'asc' ? 'asc' : 'desc';
    }

    /**
     * Apply a reaction-type filter to a likes sub-query. Operates on the
     * underlying query builder so it composes regardless of how the closure
     * builder's model generic is inferred.
     *
     * @param  Builder<Model>  $likes
     */
    private function filterByType(Builder $likes, ?string $type): void
    {
        if ($type !== null) {
            $likes->getQuery()->where('type', ReactionType::resolve($type));
        }
    }
}
