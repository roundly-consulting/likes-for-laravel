<?php

declare(strict_types=1);

namespace RoundlyConsulting\Likes\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use RoundlyConsulting\Likes\Actions\LikeAction;
use RoundlyConsulting\Likes\Actions\LikeManyAction;
use RoundlyConsulting\Likes\Actions\SwitchReactionAction;
use RoundlyConsulting\Likes\Actions\ToggleLikeAction;
use RoundlyConsulting\Likes\Actions\UnlikeAction;
use RoundlyConsulting\Likes\Actions\UnlikeManyAction;
use RoundlyConsulting\Likes\DataTransferObjects\LikeData;
use RoundlyConsulting\Likes\Models\Like;
use RoundlyConsulting\Likes\Support\LikeModel;
use RoundlyConsulting\Likes\Support\ReactionType;

/**
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
        return $this->likes()
            ->whereMorphedTo('likeable', $model)
            ->where('type', ReactionType::resolve($type))
            ->exists();
    }

    /**
     * Ensure this actor likes the model. Idempotent.
     *
     * @return bool true — a like now exists
     */
    public function like(Model $model, ?string $type = null): bool
    {
        return app(LikeAction::class)->execute(new LikeData($this, $model, $type));
    }

    /**
     * Ensure this actor does not like the model. Idempotent.
     *
     * @return bool false — no like exists
     */
    public function unlike(Model $model, ?string $type = null): bool
    {
        return app(UnlikeAction::class)->execute(new LikeData($this, $model, $type));
    }

    /**
     * Toggle the like for the model.
     *
     * @return bool true on like, false on unlike
     */
    public function toggleLike(Model $model, ?string $type = null): bool
    {
        return app(ToggleLikeAction::class)->execute(new LikeData($this, $model, $type));
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
        return app(SwitchReactionAction::class)->execute(new LikeData($this, $model, $type));
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
        app(LikeManyAction::class)->execute($this, $models, $type);
    }

    /**
     * Unlike many models at once.
     *
     * @param  iterable<Model>  $models
     */
    public function unlikeMany(iterable $models, ?string $type = null): void
    {
        app(UnlikeManyAction::class)->execute($this, $models, $type);
    }

    /**
     * A relation to the models of the given class this actor actively likes.
     * Eager-loadable, paginatable, and chainable like any Eloquent relation.
     *
     * @param  class-string<Model>  $likeableClass
     * @return MorphToMany<Model, $this>
     */
    public function likesOf(string $likeableClass): MorphToMany
    {
        $table = config('likes.table', 'likes');
        $table = is_string($table) ? $table : 'likes';

        /** @var MorphToMany<Model, $this> $relation */
        $relation = $this->morphedByMany(
            $likeableClass,
            'likeable',
            $table,
            'actor_id',
            'likeable_id',
        )
            ->wherePivot('actor_type', $this->getMorphClass())
            ->wherePivotNull('deleted_at')
            ->withPivot('type');

        return $relation;
    }

    /**
     * The models of the given class this actor actively likes, optionally
     * filtered by reaction type. Returns the relation so callers can chain
     * constraints, eager loads, or pagination and then ->get().
     *
     * @param  class-string<Model>  $likeableClass
     * @return MorphToMany<Model, $this>
     */
    public function likedItems(string $likeableClass, ?string $type = null): MorphToMany
    {
        $relation = $this->likesOf($likeableClass);

        if ($type !== null) {
            $relation->wherePivot('type', ReactionType::resolve($type));
        }

        return $relation;
    }
}
