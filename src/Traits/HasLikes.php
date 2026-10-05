<?php

declare(strict_types=1);

namespace RoundlyConsulting\Likes\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Query\Expression;
use RoundlyConsulting\Likes\DataTransferObjects\ReactionSummary;
use RoundlyConsulting\Likes\LikeManager;
use RoundlyConsulting\Likes\Models\Like;
use RoundlyConsulting\Likes\Support\ActorResolver;
use RoundlyConsulting\Likes\Support\HostSql;
use RoundlyConsulting\Likes\Support\LikeModel;
use RoundlyConsulting\Likes\Support\LikesConfig;
use RoundlyConsulting\Likes\Support\ReactionType;
use RoundlyConsulting\Likes\Support\TrendingScore;

/**
 * The likeable side: relation, scopes and feed/ranking selects. Counts, the reaction breakdown
 * and "liked by" checks read through `Likes::for($this)`.
 *
 * @phpstan-require-extends Model
 */
trait HasLikes
{
    /**
     * All likes on this model. A model that also uses GivesLikes declares `likes()` twice and
     * resolves it with `insteadof`; use likesReceived() there, which only this trait defines.
     *
     * @return MorphMany<Like, $this>
     */
    public function likes(): MorphMany
    {
        return $this->likesReceived();
    }

    /**
     * All likes on this model. The scopes below go through this relation, never `likes()`,
     * so they count received likes whichever trait's `likes()` a dual-role model keeps.
     *
     * @return MorphMany<Like, $this>
     */
    public function likesReceived(): MorphMany
    {
        $model = LikeModel::class();

        return $this->morphMany($model, 'likeable');
    }

    public function hasBeenLikedBy(Model $actor, ?string $type = null): bool
    {
        return app(LikeManager::class)->for($this)->likedBy($actor, $type);
    }

    /**
     * Readable alias of hasBeenLikedBy().
     */
    public function isLikedBy(Model $actor, ?string $type = null): bool
    {
        return $this->hasBeenLikedBy($actor, $type);
    }

    /**
     * Number of likes for this model. Uses an eager-loaded count when present —
     * "likes_count" (withLikesCount()) for all reactions, "likes_{type}_count"
     * (withLikesCount($type)) for one type — otherwise runs a live count.
     */
    public function likesCount(?string $type = null): int
    {
        return app(LikeManager::class)->for($this)->count($type);
    }

    /**
     * Eager-load the likes count: all reactions into "likes_count", or one reaction
     * type into its own "likes_{type}_count" attribute.
     *
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public function scopeWithLikesCount(Builder $query, ?string $type = null): Builder
    {
        return $query->withCount([
            'likesReceived as '.ReactionType::countAttribute($type) => function (Builder $likes) use ($type): void {
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
        return $this->scopeWithLikesCount($query, $type)->orderBy(ReactionType::countAttribute($type));
    }

    /**
     * Order by like count, most liked first.
     *
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public function scopeOrderByLikesDesc(Builder $query, ?string $type = null): Builder
    {
        return $this->scopeWithLikesCount($query, $type)->orderByDesc(ReactionType::countAttribute($type));
    }

    /**
     * Restrict to models the actor has liked.
     *
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public function scopeWhereLikedBy(Builder $query, Model $actor, ?string $type = null): Builder
    {
        return $query->whereHas('likesReceived', function (Builder $likes) use ($actor, $type): void {
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
        return $query->whereDoesntHave('likesReceived', function (Builder $likes) use ($actor, $type): void {
            $likes->whereMorphedTo('actor', $actor);
            $this->filterByType($likes, $type);
        });
    }

    /**
     * Hydrate per-row viewer state for an entire feed page in a single query:
     * an "is_liked" 0/1 flag and the viewer's "liked_reaction" type (the first
     * configured one when the viewer left several). The actor defaults to the
     * resolved auth actor; for guests it renders is_liked=0 and
     * liked_reaction=null rather than throwing.
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

        $correlate = function (QueryBuilder $sub, Expression|string $select) use ($table, $morphClass, $actor, $resolvedType): void {
            $sub->select($select)
                ->from($table)
                ->whereColumn($table.'.likeable_id', $this->qualifyColumn($this->getKeyName()))
                ->where($table.'.likeable_type', $morphClass)
                ->where($table.'.actor_id', $actor->getKey())
                ->where($table.'.actor_type', $actor->getMorphClass())
                ->whereNull($table.'.deleted_at');

            if ($resolvedType !== null) {
                $sub->where($table.'.type', $resolvedType);
            }
        };

        return $query
            ->selectSub(
                // A 0/1 flag, not the number of reactions the viewer left.
                fn (QueryBuilder $sub) => $correlate($sub, new Expression('case when count(*) > 0 then 1 else 0 end')),
                'is_liked',
            )
            ->selectSub(
                function (QueryBuilder $sub) use ($correlate, $table): void {
                    $correlate($sub, $table.'.type');

                    // Several active reactions: the first configured one wins, never an
                    // arbitrary row.
                    ReactionType::orderByPreference($sub);
                    $sub->limit(1);
                },
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
     * a boosted weighted count of recent likes (config "likes.trending"), or the
     * host's raw per-driver expression from "likes.trending.driver_expressions".
     *
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public function scopeOrderByTrending(Builder $query, string $direction = 'desc', ?string $type = null): Builder
    {
        if ($query->getQuery()->columns === null || $query->getQuery()->columns === []) {
            $query->select($query->getModel()->getTable().'.*');
        }

        // The host's per-driver override when one is configured for the driver this query
        // runs on, otherwise the portable hybrid.
        $trending = TrendingScore::trending($query->getModel()->getConnection()->getDriverName());
        $table = $this->likesTable();
        $resolvedType = $type !== null ? ReactionType::resolve($type) : null;

        $query->selectSub(function (QueryBuilder $sub) use ($trending, $table, $resolvedType): void {
            $sub->select(new HostSql('coalesce('.$trending['expression'].', 0)'))
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
        return app(LikeManager::class)->for($this)->summary($viewer);
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

    private function likesTable(): string
    {
        return LikesConfig::table();
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
