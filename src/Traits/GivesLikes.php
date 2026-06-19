<?php

declare(strict_types=1);

namespace RoundlyConsulting\Likes\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use RoundlyConsulting\Likes\Events\LikeToggled;
use RoundlyConsulting\Likes\Models\Like;

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

    public function hasLiked(Model $model): bool
    {
        return $this->likes()
            ->whereMorphedTo('likeable', $model)
            ->exists();
    }

    public function toggleLike(Model $model): bool
    {
        $like = $this->likes()
            ->whereMorphedTo('likeable', $model)
            ->firstOrCreate(values: [
                'likeable_id' => $model->getKey(),
                'likeable_type' => $model->getMorphClass(),
            ]);

        LikeToggled::dispatch($this, $model, $like->wasRecentlyCreated);

        if (! $like->wasRecentlyCreated) {
            $like->delete();

            return false;
        }

        return true;
    }
}
