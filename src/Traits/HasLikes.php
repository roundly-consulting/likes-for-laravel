<?php

declare(strict_types=1);

namespace RoundlyConsulting\Likes\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use RoundlyConsulting\Likes\Models\Like;
use RoundlyConsulting\Likes\Support\ReactionType;

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
        /** @var class-string<Like> $model */
        $model = config('likes.model', Like::class);

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
