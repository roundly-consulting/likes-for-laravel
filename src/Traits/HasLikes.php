<?php

declare(strict_types=1);

namespace RoundlyConsulting\Likes\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use RoundlyConsulting\Likes\Models\Like;

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

    public function hasBeenLikedBy(Model $actor): bool
    {
        return $this->likes()
            ->whereMorphedTo('actor', $actor)
            ->exists();
    }
}
