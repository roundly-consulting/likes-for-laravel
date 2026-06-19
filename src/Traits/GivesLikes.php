<?php

declare(strict_types=1);

namespace RoundlyConsulting\Likes\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use RoundlyConsulting\Likes\Actions\LikeAction;
use RoundlyConsulting\Likes\Actions\LikeManyAction;
use RoundlyConsulting\Likes\Actions\ToggleLikeAction;
use RoundlyConsulting\Likes\Actions\UnlikeAction;
use RoundlyConsulting\Likes\Actions\UnlikeManyAction;
use RoundlyConsulting\Likes\DataTransferObjects\LikeData;
use RoundlyConsulting\Likes\Models\Like;
use RoundlyConsulting\Likes\Support\ReactionType;

trait GivesLikes
{
    /**
     * @return MorphMany<Like, $this>
     */
    public function likes(): MorphMany
    {
        /** @var class-string<Like> $model */
        $model = config('likes.model', Like::class);

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
}
