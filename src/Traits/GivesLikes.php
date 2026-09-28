<?php

declare(strict_types=1);

namespace RoundlyConsulting\Likes\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Query\Builder as QueryBuilder;
use RoundlyConsulting\Likes\LikeManager;
use RoundlyConsulting\Likes\Models\Like;
use RoundlyConsulting\Likes\PendingLike;
use RoundlyConsulting\Likes\Support\LikeModel;
use RoundlyConsulting\Likes\Support\ReactionType;

/**
 * The actor side. Every write and check goes through the `Likes` manager as this model, so
 * `Likes::fake()` records calls made here too.
 *
 * @phpstan-require-extends Model
 */
trait GivesLikes
{
    /**
     * @return MorphMany<Like, $this>
     */
    public function likes(): MorphMany
    {
        $model = LikeModel::class();

        return $this->morphMany($model, 'actor');
    }

    public function hasLiked(Model $model, ?string $type = null): bool
    {
        return app(LikeManager::class)->for($model)->likedBy($this, $type);
    }

    /**
     * Ensure this actor likes the model. Idempotent.
     *
     * @return bool true — a like now exists
     */
    public function like(Model $model, ?string $type = null): bool
    {
        return $this->likesAs($type)->like($model);
    }

    /**
     * Ensure this actor does not like the model. Idempotent.
     *
     * @return bool false — no like exists
     */
    public function unlike(Model $model, ?string $type = null): bool
    {
        return $this->likesAs($type)->unlike($model);
    }

    /**
     * Toggle the like for the model.
     *
     * @return bool true on like, false on unlike
     */
    public function toggleLike(Model $model, ?string $type = null): bool
    {
        return $this->likesAs($type)->toggle($model);
    }

    /**
     * React to the model, switching any existing reaction in place so the actor
     * keeps exactly one active reaction per likeable. Use like() instead when
     * you want several typed reactions to co-exist.
     *
     * @return bool true — a reaction now exists
     */
    public function react(Model $model, ?string $type = null): bool
    {
        return $this->likesAs($type)->react($model);
    }

    /**
     * Readable alias of react().
     *
     * @return bool true — a reaction now exists
     */
    public function switchReaction(Model $model, ?string $type = null): bool
    {
        return $this->react($model, $type);
    }

    /**
     * Like many models at once.
     *
     * @param  iterable<Model>  $models
     */
    public function likeMany(iterable $models, ?string $type = null): void
    {
        $this->likesAs($type)->likeMany($models);
    }

    /**
     * Unlike many models at once.
     *
     * @param  iterable<Model>  $models
     */
    public function unlikeMany(iterable $models, ?string $type = null): void
    {
        $this->likesAs($type)->unlikeMany($models);
    }

    /**
     * A relation to the models of the given class this actor actively likes, each item
     * once however many reactions it has (the pivot carries the actor's earliest active
     * reaction). Eager-loadable, paginatable, and chainable like any Eloquent relation.
     *
     * @param  class-string<Model>  $likeableClass
     * @return MorphToMany<Model, $this>
     */
    public function likesOf(string $likeableClass): MorphToMany
    {
        $relation = $this->reactionsOf($likeableClass);
        $table = $relation->getTable();

        // The relation joins the likes table, so an item with several active reactions
        // would come back once per reaction (and paginate()->total() would count every
        // copy). Join only the actor's first active reaction on each item instead.
        $relation->getBaseQuery()->where($table.'.id', '=', function (QueryBuilder $first) use ($table): void {
            $first->selectRaw('min(id)')
                ->from($table.' as likes_first')
                ->whereColumn('likes_first.actor_type', $table.'.actor_type')
                ->whereColumn('likes_first.actor_id', $table.'.actor_id')
                ->whereColumn('likes_first.likeable_type', $table.'.likeable_type')
                ->whereColumn('likes_first.likeable_id', $table.'.likeable_id')
                ->whereNull('likes_first.deleted_at');
        });

        return $relation;
    }

    /**
     * The models of the given class this actor actively likes, each once, optionally
     * narrowed to one reaction type. Returns the relation so callers can chain
     * constraints, eager loads, or pagination and then ->get().
     *
     * @param  class-string<Model>  $likeableClass
     * @return MorphToMany<Model, $this>
     */
    public function likedItems(string $likeableClass, ?string $type = null): MorphToMany
    {
        if ($type === null) {
            return $this->likesOf($likeableClass);
        }

        // One row per actor + likeable + type is a unique index, so a typed relation is
        // distinct already.
        return $this->reactionsOf($likeableClass)->wherePivot('type', ReactionType::resolve($type));
    }

    /**
     * Every active reaction of this actor on models of the given class, one row each.
     *
     * @param  class-string<Model>  $likeableClass
     * @return MorphToMany<Model, $this>
     */
    private function reactionsOf(string $likeableClass): MorphToMany
    {
        $table = config('likes.table', 'likes');

        /** @var MorphToMany<Model, $this> $relation */
        $relation = $this->morphedByMany(
            $likeableClass,
            'likeable',
            is_string($table) ? $table : 'likes',
            'actor_id',
            'likeable_id',
        )
            ->wherePivot('actor_type', $this->getMorphClass())
            ->wherePivotNull('deleted_at')
            ->withPivot('type');

        return $relation;
    }

    /**
     * The manager's builder acting as this model, with the reaction type when one is given.
     */
    protected function likesAs(?string $type): PendingLike
    {
        $pending = app(LikeManager::class)->actor($this);

        return $type === null ? $pending : $pending->as($type);
    }
}
